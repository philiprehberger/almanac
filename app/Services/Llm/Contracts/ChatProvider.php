<?php

namespace App\Services\Llm\Contracts;

use App\Services\Llm\StructuredResult;

interface ChatProvider
{
    public function name(): string;

    /**
     * Run a structured-output chat completion.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  bool  $stream  Whether to also produce streamed tokens.
     */
    public function complete(array $messages, bool $stream = false): StructuredResult;
}
