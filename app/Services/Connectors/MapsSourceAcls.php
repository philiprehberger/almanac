<?php

namespace App\Services\Connectors;

trait MapsSourceAcls
{
    /**
     * Map raw source ACL entries to principal rows, failing closed: an entry
     * with no kind — or a named principal (user/group/…) with no id — is
     * dropped rather than defaulting to a workspace-wide-readable grant.
     * `public` / `workspace` ACLs legitimately carry no id and short-circuit
     * in user_can_read, so they keep the `*` sentinel.
     *
     * @param  array<int, mixed>  $raw
     * @return array<int, array{principal_kind:string, principal_external_id:string}>
     */
    protected function mapAcls(array $raw): array
    {
        $mapped = [];
        foreach ($raw as $a) {
            if (! is_array($a)) {
                continue;
            }
            $kind = (string) ($a['kind'] ?? '');
            if ($kind === '') {
                continue;
            }
            $id = (string) ($a['id'] ?? '');
            if ($id === '') {
                if ($kind === 'public' || $kind === 'workspace') {
                    $id = '*';
                } else {
                    continue;
                }
            }
            $mapped[] = ['principal_kind' => $kind, 'principal_external_id' => $id];
        }

        return $mapped;
    }
}
