<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Workspace;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiKeysController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $keys = $workspace->apiKeys()->orderByDesc('created_at')->get();
        return response()->json(['data' => $keys->map(fn ($k) => $this->serialize($k))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);

        $data = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:100'],
            'scope' => ['required', 'string', 'in:'.implode(',', ApiKey::SCOPES)],
            'ip_allowlist' => ['nullable', 'array'],
            'ip_allowlist.*' => ['string', 'regex:/^[0-9a-fA-F:.]+(\/[0-9]{1,3})?$/'],
            'expires_at' => ['nullable', 'date'],
        ])->validate();

        [$apiKey, $plaintext] = ApiKey::mint(
            workspace: $workspace,
            scope: $data['scope'],
            name: $data['name'] ?? null,
            ipAllowlist: $data['ip_allowlist'] ?? null,
            expiresAt: isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );

        AuditLogger::record($workspace, 'api_key', $apiKey->id, 'created', ['scope' => $apiKey->scope], request: $request);

        $payload = $this->serialize($apiKey);
        $payload['secret'] = $plaintext;
        return response()->json($payload, 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $workspace = $this->workspace($request);
        $key = $workspace->apiKeys()->findOrFail($id);
        $key->revoked_at = now();
        $key->save();
        AuditLogger::record($workspace, 'api_key', $id, 'revoked', request: $request);
        return response()->json(status: 204);
    }

    private function workspace(Request $request): Workspace
    {
        return $request->attributes->get('workspace');
    }

    private function serialize(ApiKey $key): array
    {
        return [
            'id' => $key->id,
            'name' => $key->name,
            'prefix' => $key->prefix,
            'last_four' => $key->last_four,
            'scope' => $key->scope,
            'ip_allowlist' => $key->ip_allowlist,
            'expires_at' => $key->expires_at?->toIso8601String(),
            'last_used_at' => $key->last_used_at?->toIso8601String(),
            'revoked_at' => $key->revoked_at?->toIso8601String(),
            'created_at' => $key->created_at?->toIso8601String(),
        ];
    }
}
