<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Query;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FeedbackController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $data = Validator::make($request->all(), [
            'query_id' => ['required', 'string', 'size:26'],
            'verdict' => ['required', 'string', 'in:up,down'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $query = Query::query()->where('workspace_id', $workspace->id)->findOrFail($data['query_id']);

        $feedback = Feedback::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'query_id' => $query->id,
            ],
            [
                'verdict' => $data['verdict'],
                'comment' => $data['comment'] ?? null,
                'created_at' => now(),
            ]
        );

        return response()->json([
            'id' => $feedback->id,
            'query_id' => $feedback->query_id,
            'verdict' => $feedback->verdict,
            'comment' => $feedback->comment,
            'created_at' => $feedback->created_at?->toIso8601String(),
        ], 201);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }
}
