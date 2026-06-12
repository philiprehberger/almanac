<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDeletionRequestJob;
use App\Models\DeletionRequest;
use App\Models\Workspace;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeletionRequestsController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $data = Validator::make($request->all(), [
            'subject_user_external_id' => ['required', 'string', 'max:200'],
            'scope' => ['nullable', 'string', 'in:queries,all'],
        ])->validate();

        $req = DeletionRequest::query()->withoutGlobalScopes()->create([
            'workspace_id' => $workspace->id,
            'subject_user_external_id' => $data['subject_user_external_id'],
            'scope' => $data['scope'] ?? DeletionRequest::SCOPE_QUERIES,
            'status' => DeletionRequest::STATUS_PENDING,
            'created_at' => now(),
        ]);

        AuditLogger::record($workspace, 'deletion_request', $req->id, 'created', ['scope' => $req->scope], request: $request);
        ProcessDeletionRequestJob::dispatch($workspace->id, $req->id);

        return response()->json([
            'id' => $req->id,
            'status' => $req->status,
            'scope' => $req->scope,
        ], 202);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        $req = DeletionRequest::query()->where('workspace_id', $workspace->id)->findOrFail($id);
        return response()->json([
            'id' => $req->id,
            'status' => $req->status,
            'scope' => $req->scope,
            'subject_user_external_id' => $req->subject_user_external_id,
            'affected_query_ids' => $req->affected_query_ids,
            'last_error' => $req->last_error,
            'created_at' => $req->created_at?->toIso8601String(),
            'completed_at' => $req->completed_at?->toIso8601String(),
        ]);
    }
}
