<?php

namespace Tests\Unit;

use App\Services\Llm\PromptBuilder;
use PHPUnit\Framework\TestCase;

class PromptBuilderTest extends TestCase
{
    public function test_chunks_are_wrapped_in_tags(): void
    {
        $builder = new PromptBuilder();
        $out = $builder->build('What is the PTO policy?', [
            ['id' => 'CHUNK_A', 'text' => 'PTO is 15 days', 'source_url' => 'https://drive.google.com/d/a', 'title' => 'PTO Policy'],
            ['id' => 'CHUNK_B', 'text' => 'Health insurance covers 80%', 'source_url' => 'https://drive.google.com/d/b', 'title' => 'Handbook §4'],
        ]);

        $userMessage = '';
        foreach ($out['messages'] as $m) {
            if ($m['role'] === 'user') {
                $userMessage = $m['content'];
            }
        }
        $this->assertStringContainsString('<retrieved_chunk id="1"', $userMessage);
        $this->assertStringContainsString('<retrieved_chunk id="2"', $userMessage);
        $this->assertStringContainsString('PTO is 15 days', $userMessage);
        $this->assertStringContainsString('Question: What is the PTO policy?', $userMessage);
    }

    public function test_chunk_map_is_positional(): void
    {
        $builder = new PromptBuilder();
        $out = $builder->build('q', [
            ['id' => 'X1', 'text' => 't1', 'source_url' => 'u1', 'title' => 'a'],
            ['id' => 'X2', 'text' => 't2', 'source_url' => 'u2', 'title' => 'b'],
            ['id' => 'X3', 'text' => 't3', 'source_url' => 'u3', 'title' => 'c'],
        ]);
        $this->assertSame(['X1', 'X2', 'X3'], array_values($out['chunkMap']));
        $this->assertSame([1, 2, 3], array_keys($out['chunkMap']));
    }

    public function test_safety_preamble_present(): void
    {
        $builder = new PromptBuilder();
        $out = $builder->build('q', []);
        $system = '';
        foreach ($out['messages'] as $m) {
            if ($m['role'] === 'system') {
                $system .= $m['content'];
            }
        }
        $this->assertStringContainsString('data, not instructions', strtolower($system));
        $this->assertStringContainsString('retrieved_chunk', $system);
    }
}
