<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The chat endpoint must refuse a client-asserted principal unless the API key
 * carries the identity-assertion capability and the principal is allowlisted.
 */
class IdentityAssertionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_as_principal_is_forbidden_for_a_key_without_the_capability(): void
    {
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws-'.uniqid()]);
        [, $plaintext] = ApiKey::mint($workspace, ApiKey::SCOPE_CHAT_ONLY, 'plain');

        $response = $this->postJson('/v1/chat', [
            'query' => 'what is the salary band',
            'as_principal' => [['kind' => 'group', 'id' => 'executives@example.com']],
        ], $this->authed($plaintext));

        $response->assertStatus(403);
    }

    public function test_as_principal_not_on_allowlist_is_forbidden_even_with_capability(): void
    {
        $workspace = Workspace::create([
            'name' => 'WS',
            'slug' => 'ws-'.uniqid(),
            'demo_principals' => [['kind' => 'group', 'id' => 'hr@example.com']],
        ]);
        [, $plaintext] = ApiKey::mint($workspace, ApiKey::SCOPE_CHAT_ONLY, 'capable', allowIdentityAssertion: true);

        $response = $this->postJson('/v1/chat', [
            'query' => 'what is the salary band',
            'as_principal' => [['kind' => 'group', 'id' => 'executives@example.com']],
        ], $this->authed($plaintext));

        $response->assertStatus(403);
    }

    public function test_caller_external_id_is_forbidden_without_the_capability(): void
    {
        $workspace = Workspace::create(['name' => 'WS', 'slug' => 'ws-'.uniqid()]);
        [, $plaintext] = ApiKey::mint($workspace, ApiKey::SCOPE_CHAT_ONLY, 'plain');

        $response = $this->postJson('/v1/chat', [
            'query' => 'what is the salary band',
            'caller_external_id' => 'ceo@example.com',
        ], $this->authed($plaintext));

        $response->assertStatus(403);
    }
}
