<?php

namespace App\Services\Llm;

use App\Models\PromptInjectionSignal;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Post-LLM safety check.
 *
 * Trip conditions:
 *   1. Image tag in the answer — Markdown `![](…)` or HTML `<img>`
 *      (exfiltration vector via image-load).
 *   2. URL/link in the answer whose host doesn't match a retrieved-source host,
 *      including protocol-relative `//host` and dangerous `data:`/`javascript:`
 *      schemes.
 *   3. Citation chunk_id not in the retrieved set (hallucinated reference).
 *
 * Any trip writes a row to prompt_injection_signals and drops confidence to
 * "low". An exfiltration-class trip (1 or 2) additionally *scrubs* the offending
 * content out of the answer and discards the token stream — detection alone
 * does not stop exfiltration, so the answer that reaches the client is the
 * redacted one. Returns the sanitized result + the list of trips.
 */
final class OutputFilter
{
    /** Matches inline citation markers in tolerant form: <cite id="7"/>, <cite id='7' >, <cite id="7"></cite>. */
    private const CITE_MARKER_PATTERN = '/<cite\s+id=["\'](\d+)["\']\s*\/?>(?:<\/cite>)?/i';

    /**
     * @param  array<int, array{id:string, source_url:string}>  $retrievedChunks
     * @param  array<int, string>  $chunkMap  positional id => chunk ULID
     * @return array{result:StructuredResult, trips:array<int, array{signal_kind:string, details:array<string,mixed>}>}
     */
    public function check(StructuredResult $result, array $retrievedChunks, array $chunkMap): array
    {
        $allowedHosts = $this->allowedHosts($retrievedChunks);
        $trips = [];
        $answer = $result->answer;

        // 1) Image tag exfil — Markdown or HTML.
        if (preg_match('/!\[[^\]]*\]\([^)]+\)/', $answer) || preg_match('/<img\b[^>]*>/i', $answer)) {
            $trips[] = [
                'signal_kind' => PromptInjectionSignal::SIGNAL_IMAGE_TAG,
                'details' => ['answer_excerpt' => Str::limit($answer, 200)],
            ];
        }

        // 2) URL/link outside retrieved domains (http/https + protocol-relative).
        $urlPattern = '/(?:https?:\/\/|\/\/)[^\s)<>"\']+/i';
        if (preg_match_all($urlPattern, $answer, $matches)) {
            foreach ($matches[0] as $url) {
                $host = $this->urlHost($url);
                if ($host === null || ! $this->hostAllowed($host, $allowedHosts)) {
                    $trips[] = [
                        'signal_kind' => PromptInjectionSignal::SIGNAL_URL_OUTSIDE_SOURCES,
                        'details' => ['url' => $url, 'allowed_hosts' => array_values($allowedHosts)],
                    ];
                }
            }
        }

        // 2b) Dangerous non-navigational schemes are never legitimate here.
        if (preg_match('/\b(?:data|javascript|vbscript):/i', $answer)) {
            $trips[] = [
                'signal_kind' => PromptInjectionSignal::SIGNAL_URL_OUTSIDE_SOURCES,
                'details' => ['url' => 'dangerous-scheme', 'answer_excerpt' => Str::limit($answer, 200)],
            ];
        }

        // 3) Hallucinated citation chunk_id
        foreach ($result->citations as $cite) {
            $id = (int) ($cite['chunk_id'] ?? 0);
            if (! array_key_exists($id, $chunkMap)) {
                $trips[] = [
                    'signal_kind' => PromptInjectionSignal::SIGNAL_HALLUCINATED_CITATION,
                    'details' => ['claimed_chunk_id' => $id, 'allowed_ids' => array_keys($chunkMap)],
                ];
            }
        }
        // also inline <cite id="N"/> — tolerate single quotes / spacing variants.
        if (preg_match_all(self::CITE_MARKER_PATTERN, $answer, $inlineMatches)) {
            foreach ($inlineMatches[1] as $idStr) {
                $id = (int) $idStr;
                if (! array_key_exists($id, $chunkMap)) {
                    $trips[] = [
                        'signal_kind' => PromptInjectionSignal::SIGNAL_HALLUCINATED_CITATION,
                        'details' => ['claimed_chunk_id' => $id, 'source' => 'inline_cite'],
                    ];
                }
            }
        }

        if ($trips === []) {
            return ['result' => $result, 'trips' => []];
        }

        $exfil = array_filter($trips, fn ($t) => in_array($t['signal_kind'], [
            PromptInjectionSignal::SIGNAL_IMAGE_TAG,
            PromptInjectionSignal::SIGNAL_URL_OUTSIDE_SOURCES,
        ], true));

        $sanitizedAnswer = $answer;
        $tokenStream = $result->tokenStream;

        if ($exfil !== []) {
            // Detection is not enough — remove the exfil vectors from the text
            // the client will render, and drop the token stream so the streamer
            // re-emits the scrubbed answer instead of the raw tokens.
            $sanitizedAnswer = $this->scrubAnswer($sanitizedAnswer, $allowedHosts);
            $tokenStream = null;
        }

        // Strip inline <cite/> markers that point outside the retrieved set so a
        // hallucinated reference can never render.
        $sanitizedAnswer = $this->stripUnknownCiteMarkers($sanitizedAnswer, $chunkMap);

        $sanitized = new StructuredResult(
            answer: $sanitizedAnswer,
            citations: array_values(array_filter(
                $result->citations,
                fn ($c) => array_key_exists((int) ($c['chunk_id'] ?? 0), $chunkMap)
            )),
            confidence: 'low',
            tokensIn: $result->tokensIn,
            tokensOut: $result->tokensOut,
            costUsd: $result->costUsd,
            model: $result->model,
            tokenStream: $tokenStream,
        );

        return ['result' => $sanitized, 'trips' => $trips];
    }

    public function recordTrips(Workspace $workspace, ?string $queryId, array $trips): void
    {
        foreach ($trips as $t) {
            PromptInjectionSignal::query()->withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'query_id' => $queryId,
                'signal_kind' => $t['signal_kind'],
                'details' => $t['details'],
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int, array{source_url:string}>  $chunks
     * @return array<string, true>
     */
    private function allowedHosts(array $chunks): array
    {
        $hosts = [];
        foreach ($chunks as $c) {
            $h = parse_url($c['source_url'], PHP_URL_HOST);
            if (is_string($h)) {
                $hosts[strtolower($h)] = true;
            }
        }
        return $hosts;
    }

    /**
     * @param  array<string, true>  $allowedHosts
     */
    private function hostAllowed(string $host, array $allowedHosts): bool
    {
        // Exact host match only. A suffix match (`str_ends_with($host, '.'.$allowed)`)
        // would trust every subdomain of a shared host — e.g. a `notion.so` or
        // `docs.google.com` source would implicitly allow `attacker.notion.so`.
        return isset($allowedHosts[strtolower($host)]);
    }

    /**
     * Host of an http/https or protocol-relative (`//host/…`) URL, or null.
     */
    private function urlHost(string $url): ?string
    {
        $normalized = str_starts_with($url, '//') ? 'https:'.$url : $url;
        $host = parse_url($normalized, PHP_URL_HOST);
        return is_string($host) ? $host : null;
    }

    /**
     * Remove exfiltration vectors (images, disallowed links, dangerous schemes)
     * from an answer, leaving the surrounding prose intact.
     *
     * @param  array<string, true>  $allowedHosts
     */
    private function scrubAnswer(string $answer, array $allowedHosts): string
    {
        $answer = preg_replace('/!\[[^\]]*\]\([^)]*\)/', '[image removed]', $answer) ?? $answer;
        $answer = preg_replace('/<img\b[^>]*>/i', '[image removed]', $answer) ?? $answer;
        $answer = preg_replace('/\b(?:data|javascript|vbscript):[^\s)<>"\']*/i', '[link removed]', $answer) ?? $answer;

        return preg_replace_callback(
            '/(?:https?:\/\/|\/\/)[^\s)<>"\']+/i',
            function (array $m) use ($allowedHosts): string {
                $host = $this->urlHost($m[0]);
                return ($host !== null && $this->hostAllowed($host, $allowedHosts)) ? $m[0] : '[link removed]';
            },
            $answer,
        ) ?? $answer;
    }

    /**
     * Drop inline <cite/> markers whose positional id is not in the retrieved
     * set, so a hallucinated reference cannot be rendered by the client.
     *
     * @param  array<int, string>  $chunkMap
     */
    private function stripUnknownCiteMarkers(string $answer, array $chunkMap): string
    {
        return preg_replace_callback(
            self::CITE_MARKER_PATTERN,
            fn (array $m): string => array_key_exists((int) $m[1], $chunkMap) ? $m[0] : '',
            $answer,
        ) ?? $answer;
    }
}
