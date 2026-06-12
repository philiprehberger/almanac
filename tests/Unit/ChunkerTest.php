<?php

namespace Tests\Unit;

use App\Services\Embed\Chunker;
use PHPUnit\Framework\TestCase;

class ChunkerTest extends TestCase
{
    public function test_empty_input_yields_no_chunks(): void
    {
        $c = new Chunker();
        $this->assertSame([], $c->chunk(''));
        $this->assertSame([], $c->chunk("\n\n\n"));
    }

    public function test_short_input_yields_one_chunk(): void
    {
        $c = new Chunker();
        $out = $c->chunk('A single short paragraph that does not span the chunk target.');
        $this->assertCount(1, $out);
        $this->assertSame(0, $out[0]['seq']);
        $this->assertStringContainsString('single short paragraph', $out[0]['text']);
    }

    public function test_long_input_splits_into_multiple_chunks(): void
    {
        $c = new Chunker();
        $body = str_repeat("This is one sentence in a long document with multiple sentences. ", 200);
        $out = $c->chunk($body);
        $this->assertGreaterThan(1, count($out));
        // Sequential seq numbers.
        foreach ($out as $i => $chunk) {
            $this->assertSame($i, $chunk['seq']);
        }
    }

    public function test_token_count_is_approx(): void
    {
        $c = new Chunker();
        $out = $c->chunk('A short text of about thirty characters.');
        $this->assertGreaterThan(0, $out[0]['token_count']);
        $this->assertLessThan(50, $out[0]['token_count']);
    }
}
