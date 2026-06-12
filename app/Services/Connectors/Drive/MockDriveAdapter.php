<?php

namespace App\Services\Connectors\Drive;

use App\Models\Connector;
use App\Services\Connectors\Contracts\ConnectorAdapter;
use App\Services\Connectors\FetchedDocument;

/**
 * Walks the fixture corpus under database/seeders/fixtures/drive/* — yields
 * FetchedDocument instances exactly as the real Drive adapter would.
 * Permissions come from a fixture YAML/JSON; ACLs are emitted unchanged.
 */
class MockDriveAdapter implements ConnectorAdapter
{
    public function kind(): string
    {
        return 'drive';
    }

    public function fetch(Connector $connector, string $mode): iterable
    {
        $fixturePath = database_path('seeders/fixtures/drive');
        if (! is_dir($fixturePath)) {
            return;
        }
        $manifestPath = $fixturePath.'/manifest.json';
        if (! file_exists($manifestPath)) {
            return;
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?? [];
        foreach ($manifest as $entry) {
            $filename = (string) ($entry['filename'] ?? '');
            $contentPath = $fixturePath.'/'.$filename;
            if (! file_exists($contentPath)) {
                continue;
            }
            yield new FetchedDocument(
                externalId: (string) ($entry['external_id'] ?? sha1($filename)),
                title: (string) ($entry['title'] ?? $filename),
                kind: (string) ($entry['kind'] ?? 'gdoc'),
                sourceUrl: (string) ($entry['source_url'] ?? "https://drive.google.com/d/{$filename}"),
                etag: (string) ($entry['etag'] ?? sha1_file($contentPath)),
                modifiedAt: isset($entry['modified_at']) ? new \DateTimeImmutable((string) $entry['modified_at']) : null,
                body: (string) file_get_contents($contentPath),
                acls: $this->mapAcls((array) ($entry['acls'] ?? [])),
            );
        }
    }

    public function revoke(Connector $connector): void
    {
        // Mock: no-op. Real adapter would call oauth2.revoke.
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array{principal_kind:string, principal_external_id:string}>
     */
    private function mapAcls(array $raw): array
    {
        return array_map(
            fn ($a) => [
                'principal_kind' => (string) ($a['kind'] ?? 'workspace'),
                'principal_external_id' => (string) ($a['id'] ?? '*'),
            ],
            $raw
        );
    }
}
