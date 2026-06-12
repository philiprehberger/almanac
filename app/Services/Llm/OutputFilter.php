<?php

namespace App\Services\Llm;

use App\Models\PromptInjectionSignal;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Post-LLM safety check.
 *
 * Trip conditions:
 *   1. Markdown image tag in the answer (exfiltration vector via image-load).
 *   2. URL in the answer whose host doesn't match a retrieved-source host.
 *   3. Citation chunk_id not in the retrieved set (hallucinated reference).
 *
 * Any trip writes a row to prompt_injection_signals and drops confidence
 * to "low". Returns a sanitized result + the list of trips.
 */
final class OutputFilter
{
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

        // 1) Image tag exfil
        if (preg_match('/!\[[^\]]*\]\([^)]+\)/', $answer)) {
            $trips[] = [
                'signal_kind' => PromptInjectionSignal::SIGNAL_IMAGE_TAG,
                'details' => ['answer_excerpt' => Str::limit($answer, 200)],
            ];
        }

        // 2) URL outside retrieved domains
        $urlPattern = '/(https?:\/\/[^\s)<>"\']+)/i';
        if (preg_match_all($urlPattern, $answer, $matches)) {
            foreach ($matches[1] as $url) {
                $host = parse_url($url, PHP_URL_HOST);
                if (! is_string($host) || ! $this->hostAllowed($host, $allowedHosts)) {
                    $trips[] = [
                        'signal_kind' => PromptInjectionSignal::SIGNAL_URL_OUTSIDE_SOURCES,
                        'details' => ['url' => $url, 'allowed_hosts' => array_values($allowedHosts)],
                    ];
                }
            }
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
        // also inline <cite id="N"/>
        if (preg_match_all('/<cite id="(\d+)"\/>/', $answer, $inlineMatches)) {
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

        if ($trips !== []) {
            $sanitized = new StructuredResult(
                answer: $result->answer,
                citations: array_values(array_filter(
                    $result->citations,
                    fn ($c) => array_key_exists((int) ($c['chunk_id'] ?? 0), $chunkMap)
                )),
                confidence: 'low',
                tokensIn: $result->tokensIn,
                tokensOut: $result->tokensOut,
                costUsd: $result->costUsd,
                model: $result->model,
                tokenStream: $result->tokenStream,
            );
            return ['result' => $sanitized, 'trips' => $trips];
        }

        return ['result' => $result, 'trips' => []];
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
        $host = strtolower($host);
        if (isset($allowedHosts[$host])) {
            return true;
        }
        foreach (array_keys($allowedHosts) as $allowed) {
            if (str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }
        return false;
    }
}
