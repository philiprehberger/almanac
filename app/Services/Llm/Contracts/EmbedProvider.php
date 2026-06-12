<?php

namespace App\Services\Llm\Contracts;

interface EmbedProvider
{
    public function name(): string;

    public function dimensions(): int;

    /**
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embed(array $texts): array;
}
