<?php

namespace App\Services\Connectors;

final readonly class FetchedDocument
{
    /**
     * @param  array<int, array{principal_kind:string, principal_external_id:string}>  $acls
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public string $kind,
        public string $sourceUrl,
        public ?string $etag,
        public ?\DateTimeInterface $modifiedAt,
        public string $body,
        public array $acls,
        public bool $deleted = false,
        public ?string $failureReason = null,
    ) {
    }

    public static function unsupported(string $externalId, string $title, string $sourceUrl, string $reason): self
    {
        return new self(
            externalId: $externalId,
            title: $title,
            kind: 'unsupported',
            sourceUrl: $sourceUrl,
            etag: null,
            modifiedAt: null,
            body: '',
            acls: [],
            failureReason: $reason,
        );
    }

    public static function deletedFromSource(string $externalId): self
    {
        return new self(
            externalId: $externalId,
            title: '',
            kind: '',
            sourceUrl: '',
            etag: null,
            modifiedAt: null,
            body: '',
            acls: [],
            deleted: true,
        );
    }
}
