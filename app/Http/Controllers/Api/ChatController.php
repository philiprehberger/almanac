<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ProblemResponse;
use App\Models\IdentityMapping;
use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;
use App\Services\Chat\ChatPipeline;
use App\Services\Chat\SseChatStreamer;
use App\Services\Cost\BudgetExceededException;
use App\Services\Retrieval\PrincipalSetMaterializer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatPipeline $pipeline,
        private readonly SseChatStreamer $streamer,
        private readonly PrincipalSetMaterializer $principals,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $data = Validator::make($request->all(), [
            'query' => ['required', 'string', 'min:2', 'max:8000'],
            'conversation_id' => ['nullable', 'string', 'size:26'],
            'as_principal' => ['nullable', 'array'],
            'as_principal.*.kind' => ['required_with:as_principal', 'string', 'max:24'],
            'as_principal.*.id' => ['required_with:as_principal', 'string', 'max:200'],
            'caller_label' => ['nullable', 'string', 'max:120'],
            'caller_external_id' => ['nullable', 'string', 'max:200'],
            'provider' => ['nullable', 'string', 'in:mock,openai,anthropic,ollama'],
        ])->validate();

        /** @var Workspace $workspace */
        $workspace = $request->attributes->get('workspace');

        $principalSet = $this->resolvePrincipalSet($workspace, $data);

        try {
            $result = $this->pipeline->run(
                workspace: $workspace,
                userQuery: (string) $data['query'],
                principalSet: $principalSet,
                conversationId: $data['conversation_id'] ?? null,
                userId: null,
                callerLabel: $data['caller_label'] ?? null,
                stream: $this->wantsSse($request),
                providerOverride: $data['provider'] ?? null,
            );
        } catch (BudgetExceededException $e) {
            return new ProblemResponse(
                status: 402,
                title: 'Budget exceeded',
                detail: sprintf(
                    'Workspace %s is at $%.4f of its $%.2f monthly cap. Resets on the 1st.',
                    $e->workspaceId,
                    $e->monthToDate,
                    $e->cap,
                ),
                type: 'https://almanac.philiprehberger.com/errors/budget-exceeded',
            );
        }

        if ($this->wantsSse($request)) {
            return $this->streamer->stream($result);
        }
        return response()->json($result->toResponseArray());
    }

    /**
     * @return array<int, array{kind:string, id:string}>
     */
    private function resolvePrincipalSet(Workspace $workspace, array $data): array
    {
        if (! empty($data['as_principal']) && is_array($data['as_principal'])) {
            return $this->principals->forSynthetic($workspace, $data['as_principal']);
        }

        if (! empty($data['caller_external_id'])) {
            $mapping = IdentityMapping::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->where('workspace_id', $workspace->id)
                ->where('source_principal_id', $data['caller_external_id'])
                ->first();
            if ($mapping && $mapping->almanac_user_id !== null) {
                return $this->principals->forUser($workspace, (int) $mapping->almanac_user_id);
            }
        }

        return $this->principals->forUser($workspace, null);
    }

    private function wantsSse(Request $request): bool
    {
        $accept = strtolower((string) $request->headers->get('Accept', ''));
        return str_contains($accept, 'text/event-stream');
    }
}
