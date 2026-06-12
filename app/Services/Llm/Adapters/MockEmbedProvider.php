<?php

namespace App\Services\Llm\Adapters;

use App\Services\Llm\Contracts\EmbedProvider;

/**
 * Deterministic 1536-dim mock embedder.
 *
 * Produces vectors via SHA-256 → bytes → centred floats. Same text → same
 * vector across runs; useful for fixture tests + repeatable mock retrieval.
 * Not a learned representation — but stable enough that the cosine ordering
 * is meaningful for the fixture corpus (where docs about distinct topics
 * yield distinct hashes).
 */
class MockEmbedProvider implements EmbedProvider
{
    private const DIM = 1536;

    public function name(): string
    {
        return 'mock-1536';
    }

    public function dimensions(): int
    {
        return self::DIM;
    }

    public function embed(array $texts): array
    {
        return array_map(fn (string $t) => $this->vectorFor($t), $texts);
    }

    /**
     * @return array<int, float>
     */
    private function vectorFor(string $text): array
    {
        // 1) Reduce text to a normalized bag-of-tokens hash sequence — gives
        //    similar texts similar vectors via token overlap.
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];
        $tokens = array_filter($tokens, fn ($t) => $t !== '');
        if ($tokens === []) {
            $tokens = ['empty'];
        }
        $vec = array_fill(0, self::DIM, 0.0);
        foreach ($tokens as $tok) {
            $hash = hash('sha256', $tok, true);
            // Use 64 bytes of the hash → 64 positions × ±1.
            for ($i = 0; $i < strlen($hash) && $i < 64; $i++) {
                $b = ord($hash[$i]);
                $pos = (($b * 257) + crc32($tok)) % self::DIM;
                $vec[$pos] += ($b & 1) === 1 ? 1.0 : -1.0;
            }
        }
        return $this->normalize($vec);
    }

    /**
     * @param  array<int, float>  $v
     * @return array<int, float>
     */
    private function normalize(array $v): array
    {
        $sum = 0.0;
        foreach ($v as $f) {
            $sum += $f * $f;
        }
        $mag = sqrt($sum);
        if ($mag === 0.0) {
            return $v;
        }
        return array_map(fn ($f) => $f / $mag, $v);
    }
}
