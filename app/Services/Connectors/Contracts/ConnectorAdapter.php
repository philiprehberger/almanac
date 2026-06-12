<?php

namespace App\Services\Connectors\Contracts;

use App\Models\Connector;
use App\Services\Connectors\FetchedDocument;

interface ConnectorAdapter
{
    public function kind(): string;

    /**
     * Walk the configured corpus / change-feed and yield FetchedDocument
     * instances. The caller (DocumentIngester) handles upsert + chunk +
     * embed-queue dispatch.
     *
     * @return iterable<int, FetchedDocument>
     */
    public function fetch(Connector $connector, string $mode): iterable;

    /**
     * Revoke source-side OAuth token (Drive `oauth2.revoke`, Slack
     * `auth.revoke`, Notion no-op). Called before deleting the connector
     * row.
     */
    public function revoke(Connector $connector): void;
}
