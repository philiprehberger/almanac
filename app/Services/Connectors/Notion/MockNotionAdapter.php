<?php

namespace App\Services\Connectors\Notion;

use App\Models\Connector;
use App\Services\Connectors\Contracts\ConnectorAdapter;
use App\Services\Connectors\FetchedDocument;

class MockNotionAdapter implements ConnectorAdapter
{
    public function kind(): string
    {
        return 'notion';
    }

    public function fetch(Connector $connector, string $mode): iterable
    {
        $fixturePath = database_path('seeders/fixtures/notion');
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
                kind: 'notion_page',
                sourceUrl: (string) ($entry['source_url'] ?? "https://notion.so/p/{$filename}"),
                etag: (string) ($entry['etag'] ?? sha1_file($contentPath)),
                modifiedAt: isset($entry['modified_at']) ? new \DateTimeImmutable((string) $entry['modified_at']) : null,
                body: (string) file_get_contents($contentPath),
                acls: array_map(
                    fn ($a) => [
                        'principal_kind' => (string) ($a['kind'] ?? 'workspace'),
                        'principal_external_id' => (string) ($a['id'] ?? '*'),
                    ],
                    (array) ($entry['acls'] ?? [])
                ),
            );
        }
    }

    public function revoke(Connector $connector): void
    {
        // Notion: no revoke endpoint; discard token locally only.
    }
}
