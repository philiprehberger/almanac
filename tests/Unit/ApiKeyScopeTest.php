<?php

namespace Tests\Unit;

use App\Models\ApiKey;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit-level scope rank check + IP-allowlist CIDR match.
 * Doesn't touch the DB.
 */
class ApiKeyScopeTest extends TestCase
{
    public function test_scope_rank_is_inclusive_upward(): void
    {
        $key = new ApiKey();
        $key->scope = ApiKey::SCOPE_ADMIN_READ;
        $this->assertTrue($key->hasScope(ApiKey::SCOPE_CHAT_ONLY));
        $this->assertTrue($key->hasScope(ApiKey::SCOPE_CHAT_FEEDBACK));
        $this->assertTrue($key->hasScope(ApiKey::SCOPE_ADMIN_READ));
        $this->assertFalse($key->hasScope(ApiKey::SCOPE_ADMIN_WRITE));
    }

    public function test_chat_only_does_not_include_feedback_or_admin(): void
    {
        $key = new ApiKey();
        $key->scope = ApiKey::SCOPE_CHAT_ONLY;
        $this->assertTrue($key->hasScope(ApiKey::SCOPE_CHAT_ONLY));
        $this->assertFalse($key->hasScope(ApiKey::SCOPE_CHAT_FEEDBACK));
        $this->assertFalse($key->hasScope(ApiKey::SCOPE_ADMIN_READ));
    }

    public function test_ip_allowlist_cidr_v4(): void
    {
        $key = new ApiKey();
        $key->ip_allowlist = ['10.0.0.0/24', '192.168.1.5'];
        $this->assertTrue($key->ipAllowed('10.0.0.7'));
        $this->assertTrue($key->ipAllowed('10.0.0.0'));
        $this->assertFalse($key->ipAllowed('10.0.1.1'));
        $this->assertTrue($key->ipAllowed('192.168.1.5'));
        $this->assertFalse($key->ipAllowed('192.168.1.6'));
    }

    public function test_empty_allowlist_allows_any(): void
    {
        $key = new ApiKey();
        $key->ip_allowlist = null;
        $this->assertTrue($key->ipAllowed('1.2.3.4'));
        $key->ip_allowlist = [];
        $this->assertTrue($key->ipAllowed('1.2.3.4'));
    }
}
