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
        $expired = DB::table('conversations')
            ->where('expires_at', '<', now())
            ->pluck('id')
            ->all();

        if ($expired === []) {
            return;
        }

        $queryIds = DB::table('queries')->whereIn('conversation_id', $expired)->pluck('id')->all();
        if ($queryIds !== []) {
            DB::table('feedback')->whereIn('query_id', $queryIds)->delete();
            DB::table('unanswered_questions')->whereIn('query_id', $queryIds)->delete();
            DB::table('prompt_injection_signals')->whereIn('query_id', $queryIds)->delete();
            DB::table('queries')->whereIn('id', $queryIds)->delete();
        }
        DB::table('conversations')->whereIn('id', $expired)->delete();
    }
}
