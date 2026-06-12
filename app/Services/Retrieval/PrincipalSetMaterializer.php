<?php

namespace App\Services\Retrieval;

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
}
