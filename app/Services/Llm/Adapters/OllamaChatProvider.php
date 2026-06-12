<?php

namespace App\Services\Llm\Adapters;

use App\Services\Llm\Contracts\ChatProvider;
use App\Services\Llm\StructuredResult;
use Illuminate\Support\Facades\Http;

class OllamaChatProvider implements ChatProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model = 'llama3',
    ) {
    }

    public function name(): string
    {
        return 'ollama:'.$this->model;
    }

    public function complete(array $messages, bool $stream = false): StructuredResult
    {
        $response = Http::timeout(120)
            ->post(rtrim($this->baseUrl, '/').'/api/chat', [
                'model' => $this->model,
                'messages' => $messages,
                'stream' => false,
                'format' => 'json',
            ])
            ->throw()
            ->json();

        $raw = (string) ($response['message']['content'] ?? '{}');
        $parsed = json_decode($raw, true) ?? [];

        return new StructuredResult(
            answer: (string) ($parsed['answer'] ?? ''),
            citations: (array) ($parsed['citations'] ?? []),
            confidence: (string) ($parsed['confidence'] ?? 'low'),
            tokensIn: (int) ($response['prompt_eval_count'] ?? 0),
            tokensOut: (int) ($response['eval_count'] ?? 0),
            costUsd: 0.0,
            model: $this->model,
            tokenStream: null,
        );
    }
}
