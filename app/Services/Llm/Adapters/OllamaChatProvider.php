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
        $parsed = json_decode($raw, true);
        $parsed = is_array($parsed) ? $parsed : [];

        // Ollama can't enforce a response schema the way the OpenAI/Anthropic
        // adapters do, so normalize the structured fields here: constrain
        // confidence to the enum and keep only well-formed citations.
        $confidence = ($parsed['confidence'] ?? null) === 'high' ? 'high' : 'low';
        $citations = [];
        foreach ((array) ($parsed['citations'] ?? []) as $c) {
            if (is_array($c) && isset($c['chunk_id']) && is_numeric($c['chunk_id'])) {
                $citations[] = ['chunk_id' => (int) $c['chunk_id']];
            }
        }

        return new StructuredResult(
            answer: (string) ($parsed['answer'] ?? ''),
            citations: $citations,
            confidence: $confidence,
            tokensIn: (int) ($response['prompt_eval_count'] ?? 0),
            tokensOut: (int) ($response['eval_count'] ?? 0),
            costUsd: 0.0,
            model: $this->model,
            tokenStream: null,
        );
    }
}
