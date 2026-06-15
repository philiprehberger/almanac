<?php

namespace Tests\Feature;

use App\Models\Connector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_a_cannot_see_workspace_b_connectors(): void
    {
        [, $keyA] = $this->freshWorkspace('Alpha');
        [$wB] = $this->freshWorkspace('Bravo');

        $this->makeConnector($wB, ['label' => 'b-drive']);

        $resp = $this->getJson('/v1/connectors', $this->authed($keyA));
        $resp->assertOk();
        $resp->assertJsonCount(0, 'data');
    }

    public function test_workspace_a_404s_on_workspace_b_connector_id(): void
    {
        [, $keyA] = $this->freshWorkspace('Alpha');
        [$wB] = $this->freshWorkspace('Bravo');

        $bConnector = $this->makeConnector($wB);
        $this->getJson("/v1/connectors/{$bConnector->id}", $this->authed($keyA))->assertStatus(404);
    }

    private function makeConnector(\App\Models\Workspace $workspace, array $overrides = []): Connector
    {
        return Connector::withoutGlobalScope(\App\Models\Scopes\WorkspaceScope::class)->create(array_merge([
            'workspace_id' => $workspace->id,
            'kind' => Connector::KIND_DRIVE,
            'label' => 'test-drive',
            'config' => new \stdClass(),
            'status' => Connector::STATUS_ACTIVE,
        ], $overrides));
    }
}
