<?php

namespace App\Services\Retrieval;

use App\Models\ApiKey;
use App\Models\IdentityMapping;
use App\Models\Scopes\WorkspaceScope;
use App\Models\Workspace;

/**
 * Resolves the caller (Almanac user id, or an anonymous demo role)
 * → a principal set of (source_kind, principal_id, principal_kind) tuples.
 *
 * Principal sets always include `public` and `workspace` synthetic principals
 * (the SQL `user_can_read` function short-circuits on either) so that ACLs
 * tagged `workspace` or `public` match every caller within the workspace.
 *
 * The materializer is fail-closed: an Almanac user with no identity mappings
 * to a source can only ever see `workspace` and `public` chunks. ACLs naming
 * a principal that no Almanac user is mapped to still get stored (so source
 * drift is auditable) but never produce a match.
 */
class PrincipalSetMaterializer
{
    /**
     * @return array<int, array{kind:string, id:string}>
     */
    public function forUser(Workspace $workspace, ?int $almanacUserId): array
    {
        $set = [
            ['kind' => 'public', 'id' => '*'],
            ['kind' => 'workspace', 'id' => $workspace->id],
        ];

        if ($almanacUserId === null) {
            return $set;
        }

        $rows = IdentityMapping::query()
            ->withoutGlobalScope(WorkspaceScope::class)
            ->where('workspace_id', $workspace->id)
            ->where('almanac_user_id', $almanacUserId)
            ->get(['source_principal_kind', 'source_principal_id']);

        foreach ($rows as $row) {
            $set[] = [
                'kind' => (string) $row->source_principal_kind,
                'id' => (string) $row->source_principal_id,
            ];
        }

        return $set;
    }

    /**
     * Used by the public demo role-toggle: synthesize a principal set
     * directly from a list of source-side principal IDs. No DB lookup.
     *
     * Callers must gate this behind {@see forAssertion}; it does not itself
     * verify the caller is entitled to the principals.
     *
     * @param  array<int, array{kind:string, id:string}>  $principals
     * @return array<int, array{kind:string, id:string}>
     */
    public function forSynthetic(Workspace $workspace, array $principals): array
    {
        return array_merge(
            [
                ['kind' => 'public', 'id' => '*'],
                ['kind' => 'workspace', 'id' => $workspace->id],
            ],
            $principals,
        );
    }

    /**
     * Resolve the principal set for an authenticated request, enforcing that
     * the API key is entitled to whatever identity it is asserting.
     *
     * - `as_principal` (raw synthetic principals, the demo role-toggle) requires
     *   the key's `allow_identity_assertion` capability AND that every asserted
     *   principal is on the workspace's demo allowlist.
     * - `caller_external_id` (server-asserted end-user identity resolved through
     *   `identity_mappings`) requires the capability only; the mapping itself is
     *   operator-controlled.
     * - Otherwise the caller sees only `public` + `workspace` chunks.
     *
     * @param  array<int, mixed>  $asPrincipal
     * @return array<int, array{kind:string, id:string}>
     *
     * @throws IdentityAssertionDenied
     */
    public function forAssertion(
        Workspace $workspace,
        ApiKey $apiKey,
        array $asPrincipal,
        ?string $callerExternalId = null,
    ): array {
        if ($asPrincipal !== []) {
            if (! $apiKey->allowsIdentityAssertion()) {
                throw new IdentityAssertionDenied('This API key may not assert principals.');
            }
            foreach ($asPrincipal as $principal) {
                if (! is_array($principal) || ! $workspace->allowsSyntheticPrincipal($principal)) {
                    throw new IdentityAssertionDenied('Asserted principal is not on the workspace demo allowlist.');
                }
            }

            return $this->forSynthetic($workspace, array_map(
                fn ($p) => ['kind' => (string) $p['kind'], 'id' => (string) $p['id']],
                $asPrincipal,
            ));
        }

        if ($callerExternalId !== null && $callerExternalId !== '') {
            if (! $apiKey->allowsIdentityAssertion()) {
                throw new IdentityAssertionDenied('This API key may not assert a caller identity.');
            }
            $mapping = IdentityMapping::query()
                ->withoutGlobalScope(WorkspaceScope::class)
                ->where('workspace_id', $workspace->id)
                ->where('source_principal_id', $callerExternalId)
                ->first();
            if ($mapping && $mapping->almanac_user_id !== null) {
                return $this->forUser($workspace, (int) $mapping->almanac_user_id);
            }
        }

        return $this->forUser($workspace, null);
    }
}
