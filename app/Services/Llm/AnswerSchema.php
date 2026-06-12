<?php

namespace App\Services\Llm;

final class AnswerSchema
{
    public const JSON_SCHEMA = [
        'name' => 'AlmanacAnswer',
        'schema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['answer', 'citations', 'confidence'],
            'properties' => [
                'answer' => [
                    'type' => 'string',
                    'description' => 'The answer text. Inline citations as <cite id="N"/> tokens.',
                ],
                'citations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['chunk_id'],
                        'properties' => [
                            'chunk_id' => ['type' => 'integer', 'minimum' => 1],
                        ],
                    ],
                ],
                'confidence' => [
                    'type' => 'string',
                    'enum' => ['low', 'high'],
                ],
            ],
        ],
    ];
}
