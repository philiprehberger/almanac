<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PruneExpiredConversationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $expiredConversationIds = fn ($sub) => $sub->select('id')->from('conversations')
            ->where('expires_at', '<', now());

        $expiredQueryIds = function ($sub) use ($expiredConversationIds) {
            $sub->select('id')->from('queries')
                ->whereIn('conversation_id', $expiredConversationIds);
        };

        // Sub-selects keep the whole prune in the database — no unbounded id
        // list is ever bound into a statement. One transaction so a mid-prune
        // failure can't leave orphaned child rows.
        DB::transaction(function () use ($expiredConversationIds, $expiredQueryIds) {
            DB::table('feedback')->whereIn('query_id', $expiredQueryIds)->delete();
            DB::table('unanswered_questions')->whereIn('query_id', $expiredQueryIds)->delete();
            DB::table('prompt_injection_signals')->whereIn('query_id', $expiredQueryIds)->delete();
            DB::table('queries')->whereIn('id', $expiredQueryIds)->delete();
            DB::table('conversations')->whereIn('id', $expiredConversationIds)->delete();
        });
    }
}
