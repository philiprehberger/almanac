<?php

namespace Tests\Unit;

use App\Models\ApiKey;
use App\Models\Workspace;
use App\Services\Retrieval\IdentityAssertionDenied;
use App\Services\Retrieval\PrincipalSetMaterializer;
use Tests\TestCase;

/**
 * The identity-assertion gate: a client may only assert principals its API key
 * is entitled to, and only ones on the workspace demo allowlist.
 */
class PrincipalSetGateTest extends TestCase
{
    private function workspace(): Workspace
    {
        return (new Workspace())->forceFill([
            'id' => '01WORKSPACETEST0000000000',
            'demo_principals' => [['kind' => 'group', 'id' => 'hr@example.com']],
        ]);
    }

    private function key(bool $allowAssertion): ApiKey
    {
        return (new ApiKey())->forceFill(['allow_identity_assertion' => $allowAssertion]);
    }

    public function test_as_principal_denied_without_capability(): void
    {
        $this->expectException(IdentityAssertionDenied::class);
        (new PrincipalSetMaterializer())->forAssertion(
            $this->workspace(),
            $this->key(false),
            [['kind' => 'group', 'id' => 'hr@example.com']],
        );
    }

    public function test_as_principal_denied_when_not_on_allowlist(): void
    {
        $this->expectException(IdentityAssertionDenied::class);
        (new PrincipalSetMaterializer())->forAssertion(
            $this->workspace(),
            $this->key(true),
            [['kind' => 'group', 'id' => 'executives@example.com']],
        );
    }

    public function test_allowlisted_principal_passes_for_capable_key(): void
    {
        $set = (new PrincipalSetMaterializer())->forAssertion(
            $this->workspace(),
            $this->key(true),
            [['kind' => 'group', 'id' => 'hr@example.com']],
        );

        $this->assertContains(['kind' => 'group', 'id' => 'hr@example.com'], $set);
        $this->assertContains(['kind' => 'public', 'id' => '*'], $set);
    }

    public function test_no_assertion_yields_public_and_workspace_only(): void
    {
        $set = (new PrincipalSetMaterializer())->forAssertion(
            $this->workspace(),
            $this->key(false),
            [],
        );

        $this->assertContains(['kind' => 'public', 'id' => '*'], $set);
        $this->assertCount(2, $set);
    }
}
