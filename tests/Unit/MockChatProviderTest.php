<?php

namespace Tests\Unit;

use App\Services\Llm\Adapters\MockChatProvider;
use PHPUnit\Framework\TestCase;

class MockChatProviderTest extends TestCase
{
    public function test_returns_high_confidence_with_chunks(): void
    {
        $provider = new MockChatProvider();
        $result = $provider->complete([
            ['role' => 'system', 'content' => 'You are Almanac.'],
            ['role' => 'user', 'content' => '<retrieved_chunk id="1" source="https://drive.example.com/a" title="A">Chunk one text.</retrieved_chunk>'.
                                            "\n\n".'Question: tell me about chunk one'],
        ]);
        $this->assertSame('high', $result->confidence);
        $this->assertNotEmpty($result->citations);
        $this->assertStringContainsString('Chunk one text', $result->answer);
        $this->assertStringContainsString('<cite id="1"/>', $result->answer);
    }

    public function test_returns_low_confidence_without_chunks(): void
    {
        $provider = new MockChatProvider();
        $result = $provider->complete([
            ['role' => 'user', 'content' => 'Question: hello'],
        ]);
        $this->assertSame('low', $result->confidence);
        $this->assertEmpty($result->citations);
    }

    public function test_streams_token_and_cite_frames(): void
    {
        $provider = new MockChatProvider();
        $result = $provider->complete([
            ['role' => 'user', 'content' => '<retrieved_chunk id="1" source="https://drive.example.com/a" title="A">Chunk one text.</retrieved_chunk>'.
                                            "\n\n".'Question: test'],
        ], stream: true);
        $this->assertNotNull($result->tokenStream);
        $frames = iterator_to_array($result->tokenStream);
        $kinds = array_column($frames, 'kind');
        $this->assertContains('token', $kinds);
        $this->assertContains('cite', $kinds);
    }
}
