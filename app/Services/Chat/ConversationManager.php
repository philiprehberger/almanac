<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Query;
use App\Models\Workspace;

class ConversationManager
{
    private const TOKEN_BUDGET = 4000;
    private const SUMMARY_TARGET = 600;

    public function getOrCreate(Workspace $workspace, ?string $conversationId, ?int $userId, ?string $callerLabel): Conversation
    {
        if ($conversationId !== null) {
            $existing = Conversation::query()->find($conversationId);
            if ($existing !== null) {
                return $existing;
            }
        }

        return Conversation::create([
            'workspace_id' => $workspace->id,
            'user_id' => $userId,
            'caller_label' => $callerLabel,
            'expires_at' => now()->addDays(30),
        ]);
    }

    /**
     * Build a sliding-window summary block when prior-turn tokens exceed budget.
     * Returns null if no summary needed yet.
     */
    public function buildSummary(Conversation $conversation): ?string
    {
        $turns = Query::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get(['query_text', 'answer_text', 'tokens_in', 'tokens_out']);

        if ($turns->isEmpty()) {
            return null;
        }

        $total = 0;
        foreach ($turns as $t) {
            $total += (int) ($t->tokens_in + $t->tokens_out);
        }
        if ($total <= self::TOKEN_BUDGET) {
            return $conversation->summary_text;
        }

        // Compact older turns; keep last 2 turns verbatim, summarize the rest.
        $older = $turns->slice(0, -2);
        if ($older->isEmpty()) {
            return $conversation->summary_text;
        }

        $lines = [];
        foreach ($older as $t) {
            $q = mb_substr((string) $t->query_text, 0, 200);
            $a = mb_substr((string) $t->answer_text, 0, 200);
            $lines[] = "- Q: {$q}\n  A: {$a}";
        }
        $summary = "Previous exchange (older turns, summarized):\n".implode("\n", $lines);
        $summary = mb_substr($summary, 0, self::SUMMARY_TARGET * 4);

        $conversation->summary_text = $summary;
        $conversation->save();

        return $summary;
    }
}
