<?php

namespace App\Services\Retrieval;

final readonly class RetrievalResult
{
    /**
     * @param  array<int, RetrievedChunk>  $chunks  ordered by descending score
     */
    public function __construct(
        public array $chunks,
        public bool $aclThin,
        public float $estimatedSelectivity,
        public int $kRequested,
        public int $kReturned,
    ) {
    }
}
