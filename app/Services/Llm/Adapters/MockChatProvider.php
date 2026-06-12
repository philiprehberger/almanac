<?php

namespace App\Services\Llm\Adapters;

use App\Services\Llm\Contracts\ChatProvider;
use App\Services\Llm\StructuredResult;

/**
 * Deterministic mock chat provider for the portfolio demo.
 *
 * Reads retrieved chunks out of the prompt's <retrieved_chunk> tags,
 * synthesises a 1-3 sentence answer that quotes each chunk's first sentence
 * with an inline <cite id="N"/> token, and emits a token stream.
 */
class MockChatProvider implements ChatProvider
{
    public function name(): string
    {
        return 'mock';
    }

    public function complete(array $messages, bool $stream = false): StructuredResult
    {
        $prompt = collect($messages)->pluck('content')->implode("\n");
        $chunks = $this->extractChunks($prompt);
        $queryText = $this->extractQueryText($messages);

        if ($chunks === []) {
            $answer = "I couldn't find anything on this in your docs. I'm logging the question — if it comes up often, your admin will know.";
            return new StructuredResult(
                answer: $answer,
                citations: [],
                confidence: 'low',
                tokensIn: max(1, (int) (strlen($prompt) / 4)),
                tokensOut: max(1, (int) (strlen($answer) / 4)),
                costUsd: 0.0,
                model: 'mock',
                tokenStream: $stream ? $this->tokenize($answer, []) : null,
            );
        }

        $sentences = [];
        $citations = [];
        foreach (array_slice($chunks, 0, 3) as $i => $c) {
            $firstSentence = $this->firstSentence($c['text']);
            $cited = rtrim($firstSentence, '.').' '.'<cite id="'.$c['id'].'"/>'.'.';
            $sentences[] = $cited;
            $citations[] = ['chunk_id' => $c['id']];
        }

        // Light query-aware framing so the answer isn't just a dump.
        $intro = $queryText !== '' ? "Here's what your docs say about \"{$queryText}\":" : 'Here\'s what your docs say:';
        $answer = $intro." \n\n".implode(' ', $sentences);

        return new StructuredResult(
            answer: $answer,
            citations: $citations,
            confidence: 'high',
            tokensIn: max(1, (int) (strlen($prompt) / 4)),
            tokensOut: max(1, (int) (strlen($answer) / 4)),
            costUsd: 0.0,
            model: 'mock',
            tokenStream: $stream ? $this->tokenize($answer, $citations) : null,
        );
    }

    /**
     * @return iterable<int, array{kind:'token'|'cite', text?:string, chunk_id?:int}>
     */
    private function tokenize(string $answer, array $citations): iterable
    {
        // Stream word-by-word; surface citation tokens as their own frames so
        // the SseChatStreamer can translate them into event: citation frames.
        $pattern = '/<cite id="(\d+)"\/>/';
        $parts = preg_split($pattern, $answer, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                yield ['kind' => 'cite', 'chunk_id' => (int) $part];
                continue;
            }
            foreach (preg_split('/(\s+)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $token) {
                if ($token === '') {
                    continue;
                }
                yield ['kind' => 'token', 'text' => $token];
            }
        }
    }

    /**
     * @return array<int, array{id:int, text:string}>
     */
    private function extractChunks(string $prompt): array
    {
        if (preg_match_all(
            '/<retrieved_chunk id="(\d+)"[^>]*>(.*?)<\/retrieved_chunk>/s',
            $prompt,
            $matches,
            PREG_SET_ORDER
        )) {
            return array_map(
                fn ($m) => ['id' => (int) $m[1], 'text' => trim($m[2])],
                $matches
            );
        }
        return [];
    }

    private function firstSentence(string $text): string
    {
        $clean = preg_replace('/\s+/', ' ', $text) ?? $text;
        if (preg_match('/^(.*?[\.\!\?])\s/', $clean, $m)) {
            return trim($m[1]);
        }
        return mb_substr($clean, 0, 200);
    }

    private function extractQueryText(array $messages): string
    {
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $content = (string) ($m['content'] ?? '');
                // PromptBuilder appends the user query after "Question: " at
                // the very end of the user message. Pull that suffix so the
                // mock-mode introduction sentence doesn't echo the entire
                // prompt context back to the caller.
                if (preg_match('/Question:\s*(.+?)\s*$/s', $content, $m)) {
                    return trim($m[1]);
                }
                return trim($content);
            }
        }
        return '';
    }
}
