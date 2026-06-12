<?php

namespace App\Services\Embed;

/**
 * Sentence-aware fixed-token chunker.
 *
 * Splits on paragraph then on sentence boundaries; emits chunks that target
 * ~400 tokens (approx 1600 chars) with an 80-token overlap. Token count is
 * estimated as ceil(chars / 4) — close enough for the 1536-dim embedder
 * without depending on tiktoken on the PHP side.
 */
class Chunker
{
    public const VERSION = 'v1';

    private const TARGET_CHARS = 1600;
    private const OVERLAP_CHARS = 320;

    /**
     * @return array<int, array{seq:int, text:string, token_count:int}>
     */
    public function chunk(string $body): array
    {
        $body = trim(preg_replace('/\r\n?/', "\n", $body) ?? '');
        if ($body === '') {
            return [];
        }

        $paragraphs = preg_split('/\n{2,}/', $body) ?: [];
        $chunks = [];
        $buffer = '';
        $seq = 0;
        foreach ($paragraphs as $p) {
            if ((mb_strlen($buffer) + mb_strlen($p)) <= self::TARGET_CHARS) {
                $buffer = $buffer === '' ? $p : $buffer."\n\n".$p;
                continue;
            }
            if ($buffer !== '') {
                $chunks[] = $this->mkChunk($buffer, $seq++);
                $tail = mb_substr($buffer, max(0, mb_strlen($buffer) - self::OVERLAP_CHARS));
                $buffer = $tail."\n\n".$p;
            } else {
                $chunks = array_merge($chunks, $this->splitLong($p, $seq));
                $seq += count($chunks);
                $buffer = '';
            }
        }
        if ($buffer !== '') {
            $chunks[] = $this->mkChunk($buffer, $seq);
        }
        return $chunks;
    }

    private function mkChunk(string $text, int $seq): array
    {
        $text = trim($text);
        return [
            'seq' => $seq,
            'text' => $text,
            'token_count' => max(1, (int) ceil(mb_strlen($text) / 4)),
        ];
    }

    /**
     * @return array<int, array{seq:int, text:string, token_count:int}>
     */
    private function splitLong(string $paragraph, int $startSeq): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [$paragraph];
        $chunks = [];
        $buffer = '';
        $seq = $startSeq;
        foreach ($sentences as $s) {
            if ((mb_strlen($buffer) + mb_strlen($s)) > self::TARGET_CHARS && $buffer !== '') {
                $chunks[] = $this->mkChunk($buffer, $seq++);
                $buffer = $s;
                continue;
            }
            $buffer = $buffer === '' ? $s : $buffer.' '.$s;
        }
        if ($buffer !== '') {
            $chunks[] = $this->mkChunk($buffer, $seq);
        }
        return $chunks;
    }
}
