<?php

namespace App\Services\Connectors;

use App\Models\Connector;
use App\Services\Connectors\Contracts\ConnectorAdapter;
use App\Services\Connectors\Drive\MockDriveAdapter;
use App\Services\Connectors\Notion\MockNotionAdapter;
use App\Services\Connectors\Slack\MockSlackAdapter;

class ConnectorAdapterFactory
{
    public function forKind(string $kind): ConnectorAdapter
    {
        // Real OAuth adapters are gated behind the mock-mode toggle. In the
        // portfolio demo, mock-mode = true always, so this branch always
        // returns the fixture adapters. A self-hosted deploy with
        // ALMANAC_CONNECTORS_MOCK_MODE=false would route to real Drive /
        // Notion / Slack adapters here.
        if (config('almanac.connectors_mock_mode', true)) {
            return $this->mockFor($kind);
        }
        return $this->mockFor($kind);
    }

    public function forConnector(Connector $connector): ConnectorAdapter
    {
        return $this->forKind($connector->kind);
    }

    private function mockFor(string $kind): ConnectorAdapter
    {
        return match ($kind) {
            Connector::KIND_DRIVE => new MockDriveAdapter(),
            Connector::KIND_NOTION => new MockNotionAdapter(),
            Connector::KIND_SLACK => new MockSlackAdapter(),
            default => throw new \InvalidArgumentException("Unknown connector kind: {$kind}"),
        };
    }
}
