<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\PromptTemplate;
use App\Models\Query;
use App\Models\Workspace;
use App\Services\Cost\BudgetEnforcer;
use App\Services\Llm\LlmAdapterFactory;
use App\Services\Llm\OutputFilter;
use App\Services\Llm\PromptBuilder;
use App\Services\Retrieval\AclAwareRetriever;
use App\Services\Retrieval\RetrievalResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Orchestrates the full chat-API flow:
 *   1. budget check
 *   2. principal-set materialization (caller upstream of this service)
 *   3. retrieval
 *   4. prompt building
 *   5. LLM call (structured output)
 *   6. output filtering
 *   7. persistence (query, citations, unanswered_questions if low-confidence)
 *
 * Returns a ChatPipelineResult to the controller, which decides whether to
 * SSE-stream or JSON-respond.
 */
class ChatPipeline
{
    public function __construct(
        private readonly AclAwareRetriever $retriever,
        private readonly PromptBuilder $promptBuilder,
        private readonly LlmAdapterFactory $llmFactory,
        private readonly OutputFilter $outputFilter,
        private readonly ConversationManager $conversationManager,
        private readonly BudgetEnforcer $budget,
    ) {
    }

    /**
     * @param  array<int, array{kind:string, id:string}>  $principalSet
     */
    public function run(
        Workspace $workspace,
        string $userQuery,
        array $principalSet,
        ?string $conversationId = null,
        ?int $userId = null,
        ?string $callerLabel = null,
        bool $stream = false,
        ?string $providerOverride = null,
    ): ChatPipelineResult {
        $this->budget->ensureUnderCap($workspace);

        $conversation = $this->conversationManager->getOrCreate($workspace, $conversationId, $userId, $callerLabel);
        $summary = $this->conversationManager->buildSummary($conversation);

        $start = hrtime(true);

        $retrieval = $this->retriever->retrieve($workspace, $userQuery, $principalSet);

        $chunksForPrompt = array_map(
            fn ($c) => [
                'id' => $c->id,
                'text' => $c->text,
                'source_url' => $c->sourceUrl,
                'title' => $c->title,
            ],
            $retrieval->chunks
        );

        $template = PromptTemplate::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->first();

        $built = $this->promptBuilder->build($userQuery, $chunksForPrompt, $template, $summary);

        $provider = $this->llmFactory->chat($providerOverride);
        $raw = $provider->complete($built['messages'], $stream);

        // chunkMap: positional id 1..K -> chunk ULID
        $retrievedAsArrays = array_map(
            fn ($c) => ['id' => $c->id, 'source_url' => $c->sourceUrl],
            $retrieval->chunks
        );
        $filtered = $this->outputFilter->check($raw, $retrievedAsArrays, $built['chunkMap']);
        $sanitized = $filtered['result'];
        $trips = $filtered['trips'];

        // Decide confidence-reason if any.
        $confidence = $sanitized->confidence;
        $confidenceReason = null;
        if ($retrieval->aclThin) {
            $confidence = 'low';
            $confidenceReason = Query::REASON_ACL_THIN;
        } elseif ($retrieval->chunks === []) {
            $confidence = 'low';
            $confidenceReason = Query::REASON_SCORE_THIN;
        } elseif ($confidence === 'low') {
            $confidenceReason = Query::REASON_MODEL_UNSURE;
        }

        // Persist the query.
        $latencyMs = (int) ((hrtime(true) - $start) / 1_000_000);
        $citationsSnapshot = $this->buildCitationsSnapshot($retrieval->chunks, $built['chunkMap'], $sanitized->citations);

        $query = Query::query()->withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'conversation_id' => $conversation->id,
            'query_text' => $userQuery,
            'principal_set' => $principalSet,
            'retrieved_chunk_ids' => array_map(fn ($c) => $c->id, $retrieval->chunks),
            'model' => $sanitized->model,
            'answer_text' => $sanitized->answer,
            'citations' => $citationsSnapshot,
            'confidence' => $confidence,
            'confidence_reason' => $confidenceReason,
            'latency_ms' => $latencyMs,
            'tokens_in' => $sanitized->tokensIn,
            'tokens_out' => $sanitized->tokensOut,
            'cost_usd' => $sanitized->costUsd,
            'created_at' => Carbon::now(),
        ]);

        $this->budget->record($workspace, $sanitized->tokensIn, $sanitized->tokensOut, (float) $sanitized->costUsd);
        $this->outputFilter->recordTrips($workspace, $query->id, $trips);

        if ($confidence === 'low') {
            \App\Models\UnansweredQuestion::query()->withoutGlobalScopes()->create([
                'workspace_id' => $workspace->id,
                'query_id' => $query->id,
                'reason' => $confidenceReason ?? Query::REASON_MODEL_UNSURE,
                'created_at' => Carbon::now(),
            ]);
        }

        return new ChatPipelineResult(
            queryId: $query->id,
            conversationId: $conversation->id,
            answer: $sanitized->answer,
            citations: $citationsSnapshot,
            confidence: $confidence,
            confidenceReason: $confidenceReason,
            costUsd: (float) $sanitized->costUsd,
            tokensIn: $sanitized->tokensIn,
            tokensOut: $sanitized->tokensOut,
            latencyMs: $latencyMs,
            retrieval: $retrieval,
            chunkMap: $built['chunkMap'],
            tokenStream: $sanitized->tokenStream,
            promptInjectionTrips: $trips,
        );
    }

    /**
     * @param  array<int, \App\Services\Retrieval\RetrievedChunk>  $chunks
     * @param  array<int, string>  $chunkMap  positional id -> chunk ULID
     * @param  array<int, array{chunk_id:int}>  $citations
     */
    private function buildCitationsSnapshot(array $chunks, array $chunkMap, array $citations): array
    {
        $byUlid = [];
        foreach ($chunks as $c) {
            $byUlid[$c->id] = $c;
        }
        $snapshots = [];
        // Each cited positional chunk_id -> snapshot
        $seen = [];
        foreach ($citations as $cite) {
            $pos = (int) ($cite['chunk_id'] ?? 0);
            $ulid = $chunkMap[$pos] ?? null;
            if ($ulid === null || isset($seen[$pos])) {
                continue;
            }
            $seen[$pos] = true;
            $chunk = $byUlid[$ulid] ?? null;
            if ($chunk === null) {
                continue;
            }
            $snapshots[] = $chunk->toFrozenSnapshot($pos);
        }
        return $snapshots;
    }
}
