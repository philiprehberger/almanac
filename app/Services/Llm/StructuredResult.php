<?php

namespace App\Services\Llm;

final readonly class StructuredResult
{
    /**
     * @param  iterable<string>|null  $tokenStream  Optional streamed text frames.
     * @param  array<int, array{chunk_id:int}>  $citations
     */
    public function __construct(
        public string $answer,
        public array $citations,
        public string $confidence,
        public int $tokensIn,
        public int $tokensOut,
        public float $costUsd,
        public string $model,
        public ?iterable $tokenStream = null,
    ) {
    }
}
