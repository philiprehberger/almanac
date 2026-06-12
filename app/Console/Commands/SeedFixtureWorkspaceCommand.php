<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Connector;
use App\Models\PromptTemplate;
use App\Models\Workspace;
use App\Services\Connectors\DocumentIngester;
use App\Services\Workspaces\HnswIndexManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('almanac:seed-fixture-workspace {--slug=demo}')]
#[Description('Seed the public-demo workspace, mock-mode connectors, and the fixture corpus.')]
class SeedFixtureWorkspaceCommand extends Command
{
    public function handle(
        HnswIndexManager $hnsw,
        DocumentIngester $ingester,
    ): int {
        $slug = $this->option('slug') ?? 'demo';

        /** @var Workspace $workspace */
        $workspace = Workspace::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => Str::title($slug).' (public demo)',
                'monthly_budget_usd' => (float) config('almanac.demo.monthly_budget_usd', 25),
                'allowed_chat_origins' => ['https://almanac.philiprehberger.com', 'http://localhost:3000'],
            ],
        );

        $this->line("Workspace: {$workspace->slug} ({$workspace->id})");

        $hnsw->ensure($workspace);
        $this->line('  ✓ HNSW partial index ensured');

        PromptTemplate::firstOrCreate(
            [
                'workspace_id' => $workspace->id,
                'name' => PromptTemplate::NAME_DEFAULT,
                'version' => 'v1',
            ],
            [
                'system_prompt' => PromptTemplate::defaultSystemPrompt(),
                'chunk_wrapper_template' => PromptTemplate::defaultChunkWrapper(),
                'is_active' => true,
            ],
        );
        $this->line('  ✓ Default prompt template ensured');

        foreach ([Connector::KIND_DRIVE, Connector::KIND_NOTION, Connector::KIND_SLACK] as $kind) {
            /** @var Connector $connector */
            $connector = Connector::firstOrCreate(
                ['workspace_id' => $workspace->id, 'kind' => $kind],
                [
                    'label' => Str::title($kind).' (fixture)',
                    'config' => ['mock_mode' => true],
                    'status' => Connector::STATUS_ACTIVE,
                ],
            );
            $run = $ingester->run($workspace, $connector);
            $this->line(sprintf(
                '  ✓ %s connector ingested: added=%d updated=%d removed=%d failed=%d',
                $kind,
                $run->docs_added,
                $run->docs_updated,
                $run->docs_removed,
                $run->docs_failed,
            ));
        }

        $existingKey = ApiKey::query()->where('workspace_id', $workspace->id)
            ->where('scope', ApiKey::SCOPE_CHAT_ONLY)
            ->whereNull('revoked_at')
            ->first();
        if ($existingKey === null) {
            [$key, $plaintext] = ApiKey::mint($workspace, ApiKey::SCOPE_CHAT_ONLY, name: 'Public demo key');
            $this->newLine();
            $this->comment('Public demo API key (record this now, only shown once):');
            $this->line("  <fg=cyan>{$plaintext}</>");
        }

        $this->newLine();
        $this->info("Fixture workspace ready: {$workspace->slug}");
        return self::SUCCESS;
    }
}
