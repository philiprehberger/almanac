<?php

namespace Tests\Unit;

use App\Services\Llm\Adapters\MockEmbedProvider;
use PHPUnit\Framework\TestCase;

class MockEmbedProviderTest extends TestCase
{
    public function test_dim_is_1536(): void
    {
        $p = new MockEmbedProvider();
        $this->assertSame(1536, $p->dimensions());
        $vecs = $p->embed(['hello world']);
        $this->assertCount(1, $vecs);
        $this->assertCount(1536, $vecs[0]);
    }

    public function test_deterministic_for_same_text(): void
    {
        $p = new MockEmbedProvider();
        $a = $p->embed(['the quick brown fox'])[0];
        $b = $p->embed(['the quick brown fox'])[0];
        $this->assertSame($a, $b);
    }

    public function test_different_texts_yield_different_vectors(): void
    {
        $p = new MockEmbedProvider();
        $a = $p->embed(['the quick brown fox'])[0];
        $b = $p->embed(['a totally different sentence about cats'])[0];
        $this->assertNotSame($a, $b);
    }

    public function test_vectors_are_unit_normalized(): void
    {
        $p = new MockEmbedProvider();
        $v = $p->embed(['some text'])[0];
        $sum = 0.0;
        foreach ($v as $f) {
            $sum += $f * $f;
        }
        $mag = sqrt($sum);
        $this->assertEqualsWithDelta(1.0, $mag, 0.001);
    }
}
