<?php

namespace App\Services\Llm\Adapters;

use App\Services\Llm\AnswerSchema;
use App\Services\Llm\Contracts\ChatProvider;
use App\Services\Llm\StructuredResult;
use Illuminate\Support\Facades\Http;

class OpenAiChatProvider implements ChatProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o-mini',
        private readonly float $inputCostPer1k = 0.00015,
        private readonly float $outputCostPer1k = 0.0006,
    ) {
    }

    public function name(): string
    {
        return 'openai:'.$this->model;
    }

    public function complete(array $messages, bool $stream = false): StructuredResult
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(45)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => $messages,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => array_merge(
                        ['strict' => true],
                        AnswerSchema::JSON_SCHEMA,
                    ),
                ],
                'temperature' => 0.2,
            ])
            ->throw()
            ->json();

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $data = json_decode($content, true) ?? [];

        $tokensIn = (int) ($response['usage']['prompt_tokens'] ?? 0);
        $tokensOut = (int) ($response['usage']['completion_tokens'] ?? 0);
        $cost = ($tokensIn / 1000) * $this->inputCostPer1k
              + ($tokensOut / 1000) * $this->outputCostPer1k;

        return new StructuredResult(
            answer: (string) ($data['answer'] ?? ''),
            citations: (array) ($data['citations'] ?? []),
            confidence: (string) ($data['confidence'] ?? 'low'),
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costUsd: $cost,
            model: $this->model,
            tokenStream: null,
        );
    }
}
