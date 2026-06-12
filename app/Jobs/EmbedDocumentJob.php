<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\EmbedJobDlq;
use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use App\Services\Embed\Chunker;
use App\Services\Llm\LlmAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmbedDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly string $workspaceId,
        public readonly string $documentId,
        public readonly string $body,
    ) {
    }

    public function handle(Chunker $chunker, LlmAdapterFactory $llmFactory): void
    {
        /** @var Workspace|null $workspace */
        $workspace = Workspace::query()->find($this->workspaceId);
        /** @var Document|null $document */
        $document = Document::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->find($this->documentId);
        if ($workspace === null || $document === null) {
            return;
        }

        $embedder = $llmFactory->embed();

        $chunks = $chunker->chunk($this->body);
        if ($chunks === []) {
            $document->forceFill([
                'embed_status' => Document::EMBED_UNSUPPORTED,
                'failure_reason' => 'No extractable text.',
            ])->save();
            return;
        }
        $texts = array_map(fn ($c) => $c['text'], $chunks);
        $vectors = $embedder->embed($texts);

        DB::transaction(function () use ($workspace, $document, $chunks, $vectors, $embedder) {
            DB::table('doc_chunks')->where('document_id', $document->id)->delete();

            foreach ($chunks as $i => $c) {
                DB::statement(
                    'INSERT INTO doc_chunks
                        (id, workspace_id, document_id, seq, text, token_count, chunker_version, embedder_version, embedding, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?::vector, NOW(), NOW())',
                    [
                        (string) Str::ulid(),
                        $workspace->id,
                        $document->id,
                        $c['seq'],
                        $c['text'],
                        $c['token_count'],
                        Chunker::VERSION,
                        $embedder->name(),
                        '['.implode(',', array_map(
                            fn ($f) => rtrim(rtrim(number_format((float) $f, 6, '.', ''), '0'), '.'),
                            $vectors[$i] ?? []
                        )).']',
                    ]
                );
            }

            $document->forceFill([
                'embed_status' => Document::EMBED_EMBEDDED,
                'embedded_at' => now(),
                'failure_reason' => null,
            ])->save();
        });
    }

    public function failed(\Throwable $e): void
    {
        EmbedJobDlq::query()->withoutGlobalScopes()->create([
            'workspace_id' => $this->workspaceId,
            'document_id' => $this->documentId,
            'attempts' => $this->tries,
            'last_error' => $e->getMessage(),
            'payload' => [
                'body_length' => strlen($this->body),
            ],
            'created_at' => now(),
        ]);

        Document::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('id', $this->documentId)
            ->update([
                'embed_status' => Document::EMBED_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);
    }
}
