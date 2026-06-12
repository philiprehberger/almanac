<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ProblemResponse;
use App\Models\Document;
use App\Models\Workspace;
use App\Services\Retrieval\PrincipalSetMaterializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fetch source metadata for a cited document. ACL-checked: the caller's
 * principal-set must include a `user_can_read` match for the document.
 *
 * Returns title + source URL + last-modified — not the full body.
 */
class SourcesController extends Controller
{
    public function __construct(
        private readonly PrincipalSetMaterializer $principals,
    ) {
    }

    public function show(Request $request, string $docId): Response
    {
        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        /** @var Document|null $document */
        $document = Document::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('deleted_at')
            ->find($docId);

        if ($document === null) {
            return new ProblemResponse(
                status: 404,
                title: 'Not found',
                detail: 'No such document or not visible to caller.',
            );
        }

        $set = $this->principals->forSynthetic(
            $workspace,
            (array) $request->input('as_principal', []),
        );

        $hit = DB::selectOne(
            'SELECT user_can_read(?::jsonb, ?) AS can',
            [json_encode($set), $document->id]
        );
        if (! ($hit?->can ?? false)) {
            return new ProblemResponse(
                status: 404,
                title: 'Not found',
                detail: 'No such document or not visible to caller.',
            );
        }

        return response()->json([
            'id' => $document->id,
            'title' => $document->title,
            'kind' => $document->kind,
            'source_url' => $document->source_url,
            'modified_at' => $document->modified_at?->toIso8601String(),
            'embedded_at' => $document->embedded_at?->toIso8601String(),
        ]);
    }
}
