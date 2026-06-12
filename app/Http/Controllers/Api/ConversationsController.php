<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Query;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationsController extends Controller
{
    public function show(Request $request, string $id): JsonResponse
    {
        $workspace = $this->workspace($request);
        /** @var Conversation $conv */
        $conv = $workspace->conversations()->findOrFail($id);
        $queries = Query::query()
            ->where('conversation_id', $conv->id)
            ->orderBy('created_at')
            ->get(['id', 'query_text', 'answer_text', 'citations', 'confidence', 'created_at']);

        return response()->json([
            'id' => $conv->id,
            'caller_label' => $conv->caller_label,
            'expires_at' => $conv->expires_at?->toIso8601String(),
            'summary_text' => $conv->summary_text,
            'turns' => $queries->map(fn ($q) => [
                'id' => $q->id,
                'query' => $q->query_text,
                'answer' => $q->answer_text,
                'citations' => $q->citations,
                'confidence' => $q->confidence,
                'created_at' => $q->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }
}
