<?php

namespace Tests\Unit;

use App\Models\PromptInjectionSignal;
use App\Services\Llm\OutputFilter;
use App\Services\Llm\StructuredResult;
use PHPUnit\Framework\TestCase;

/**
 * Asserts the four output-filter guarantees:
 *   1. URLs in the answer not matching a retrieved source domain trip.
 *   2. Markdown image tags trip.
 *   3. Cited chunk_ids not in the retrieved set trip.
 *   4. Clean answers do NOT trip.
 *
 * Pure unit-level — no DB needed. The recordTrips() side-effect is exercised
 * separately in tests/Feature/PromptInjectionTest.
 */
class OutputFilterTest extends TestCase
{
    private function retrievedChunks(): array
    {
        return [
            ['id' => 'CHUNK_A', 'source_url' => 'https://drive.google.com/d/abc'],
            ['id' => 'CHUNK_B', 'source_url' => 'https://notion.so/p/xyz'],
        ];
    }

    private function chunkMap(): array
    {
        return [1 => 'CHUNK_A', 2 => 'CHUNK_B'];
    }

    private function makeResult(string $answer, array $citations = [], string $confidence = 'high'): StructuredResult
    {
        return new StructuredResult(
            answer: $answer,
            citations: $citations,
            confidence: $confidence,
            tokensIn: 100,
            tokensOut: 50,
            costUsd: 0.001,
            model: 'mock',
        );
    }

    public function test_url_outside_sources_trips(): void
    {
        $filter = new OutputFilter();
        $result = $this->makeResult('Please visit https://attacker.example.com/leak for more info.');
        $out = $filter->check($result, $this->retrievedChunks(), $this->chunkMap());
        $this->assertSame('low', $out['result']->confidence);
        $this->assertNotEmpty($out['trips']);
        $kinds = array_column($out['trips'], 'signal_kind');
        $this->assertContains(PromptInjectionSignal::SIGNAL_URL_OUTSIDE_SOURCES, $kinds);
    }

    public function test_url_inside_sources_does_not_trip(): void
    {
        $filter = new OutputFilter();
        $result = $this->makeResult('See https://drive.google.com/d/abc for details.');
        $out = $filter->check($result, $this->retrievedChunks(), $this->chunkMap());
        $this->assertEmpty($out['trips']);
        $this->assertSame('high', $out['result']->confidence);
    }

    public function test_image_tag_trips(): void
    {
        $filter = new OutputFilter();
        $result = $this->makeResult('Here is an image: ![exfil](https://attacker.example.com/pixel.png)');
        $out = $filter->check($result, $this->retrievedChunks(), $this->chunkMap());
        $kinds = array_column($out['trips'], 'signal_kind');
        $this->assertContains(PromptInjectionSignal::SIGNAL_IMAGE_TAG, $kinds);
        $this->assertSame('low', $out['result']->confidence);
    }

    public function test_hallucinated_citation_trips(): void
    {
        $filter = new OutputFilter();
        $result = $this->makeResult('A claim <cite id="99"/>', [['chunk_id' => 99]]);
        $out = $filter->check($result, $this->retrievedChunks(), $this->chunkMap());
        $kinds = array_column($out['trips'], 'signal_kind');
        $this->assertContains(PromptInjectionSignal::SIGNAL_HALLUCINATED_CITATION, $kinds);
        // Hallucinated citations are stripped from the sanitized result.
        $this->assertEmpty($out['result']->citations);
    }

    public function test_clean_answer_does_not_trip(): void
    {
        $filter = new OutputFilter();
        $result = $this->makeResult(
            'New employees accrue 15 days <cite id="1"/>. Carryover capped at 40 hours <cite id="2"/>.',
            [['chunk_id' => 1], ['chunk_id' => 2]],
        );
        $out = $filter->check($result, $this->retrievedChunks(), $this->chunkMap());
        $this->assertEmpty($out['trips']);
        $this->assertSame('high', $out['result']->confidence);
    }
}
