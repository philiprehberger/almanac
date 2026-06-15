<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeysApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mint_key_returns_plaintext_once(): void
    {
        [, $key] = $this->freshWorkspace();

        $resp = $this->postJson('/v1/api-keys', [
            'name' => 'CI',
            'scope' => ApiKey::SCOPE_CHAT_ONLY,
        ], $this->authed($key));

        $resp->assertCreated();
        $resp->assertJsonStructure(['id', 'secret', 'prefix', 'last_four', 'scope']);
        $resp->assertJsonPath('scope', ApiKey::SCOPE_CHAT_ONLY);
        $this->assertStringStartsWith('alm_', $resp->json('secret'));
    }

    public function test_non_admin_key_cannot_mint_keys(): void
    {
        [$workspace] = $this->freshWorkspace();
        [, $limited] = ApiKey::mint($workspace, ApiKey::SCOPE_CHAT_ONLY, 'limited');

        $this->postJson('/v1/api-keys', ['scope' => ApiKey::SCOPE_CHAT_ONLY], $this->authed($limited))
            ->assertStatus(403);
    }

    public function test_revoked_key_unusable(): void
    {
        [, $key] = $this->freshWorkspace();
        $resp = $this->postJson('/v1/api-keys', ['scope' => ApiKey::SCOPE_ADMIN_READ], $this->authed($key));
        $id = $resp->json('id');
        $secret = $resp->json('secret');

        $this->deleteJson("/v1/api-keys/{$id}", [], $this->authed($key))->assertNoContent();
        $this->getJson('/v1/connectors', $this->authed($secret))->assertStatus(401);
    }
}
