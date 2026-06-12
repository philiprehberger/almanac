<?php

namespace App\Services\Chat;

use App\Services\Retrieval\RetrievalResult;

final readonly class ChatPipelineResult
{
    public function __construct(
        public string $queryId,
        public string $conversationId,
        public string $answer,
        public array $citations,
        public string $confidence,
        public ?string $confidenceReason,
        public float $costUsd,
        public int $tokensIn,
        public int $tokensOut,
        public int $latencyMs,
        public RetrievalResult $retrieval,
        /** @var array<int, string> */
        public array $chunkMap,
        public ?iterable $tokenStream = null,
        public array $promptInjectionTrips = [],
    ) {
    }

    public function toResponseArray(): array
    {
        return [
            'query_id' => $this->queryId,
            'conversation_id' => $this->conversationId,
            'answer' => $this->answer,
            'citations' => $this->citations,
            'confidence' => $this->confidence,
            'confidence_reason' => $this->confidenceReason,
            'cost_usd' => round($this->costUsd, 6),
            'tokens_in' => $this->tokensIn,
            'tokens_out' => $this->tokensOut,
            'latency_ms' => $this->latencyMs,
        ];
    }
}
