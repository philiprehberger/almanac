<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ProblemResponse;
use App\Models\ApiKey;
use App\Models\Document;
use App\Models\Workspace;
use App\Services\Retrieval\IdentityAssertionDenied;
use App\Services\Retrieval\PrincipalSetMaterializer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
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
        $data = Validator::make($request->all(), [
            'as_principal' => ['nullable', 'array'],
            'as_principal.*.kind' => ['required_with:as_principal', 'string', 'max:24'],
            'as_principal.*.id' => ['required_with:as_principal', 'string', 'max:200'],
        ])->validate();

        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

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

        try {
            $set = $this->principals->forAssertion(
                workspace: $workspace,
                apiKey: $apiKey,
                asPrincipal: (array) ($data['as_principal'] ?? []),
            );
        } catch (IdentityAssertionDenied $e) {
            return new ProblemResponse(
                status: 403,
                title: 'Forbidden',
                detail: $e->getMessage(),
            );
        }

        $hit = DB::selectOne(
            'SELECT user_can_read(?::jsonb, ?, ?) AS can',
            [json_encode($set), $document->id, $workspace->id]
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
