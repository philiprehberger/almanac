<?php

namespace App\Services\Llm\Adapters;

use App\Services\Llm\AnswerSchema;
use App\Services\Llm\Contracts\ChatProvider;
use App\Services\Llm\StructuredResult;
use Illuminate\Support\Facades\Http;

class AnthropicChatProvider implements ChatProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-sonnet-4-6',
        private readonly float $inputCostPer1k = 0.003,
        private readonly float $outputCostPer1k = 0.015,
    ) {
    }

    public function name(): string
    {
        return 'anthropic:'.$this->model;
    }

    public function complete(array $messages, bool $stream = false): StructuredResult
    {
        $system = '';
        $convo = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'system') {
                $system .= ($system === '' ? '' : "\n\n").$m['content'];
                continue;
            }
            $convo[] = $m;
        }

        $tool = [
            'name' => 'AlmanacAnswer',
            'description' => 'Return the structured answer.',
            'input_schema' => AnswerSchema::JSON_SCHEMA['schema'],
        ];

        $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(45)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => 1024,
                'system' => $system,
                'messages' => $convo,
                'tools' => [$tool],
                'tool_choice' => ['type' => 'tool', 'name' => 'AlmanacAnswer'],
            ])
            ->throw()
            ->json();

        $blocks = $response['content'] ?? [];
        $payload = [];
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'tool_use' && ($b['name'] ?? '') === 'AlmanacAnswer') {
                $payload = (array) ($b['input'] ?? []);
                break;
            }
        }

        $tokensIn = (int) ($response['usage']['input_tokens'] ?? 0);
        $tokensOut = (int) ($response['usage']['output_tokens'] ?? 0);
        $cost = ($tokensIn / 1000) * $this->inputCostPer1k
              + ($tokensOut / 1000) * $this->outputCostPer1k;

        return new StructuredResult(
            answer: (string) ($payload['answer'] ?? ''),
            citations: (array) ($payload['citations'] ?? []),
            confidence: (string) ($payload['confidence'] ?? 'low'),
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costUsd: $cost,
            model: $this->model,
            tokenStream: null,
        );
    }
}
