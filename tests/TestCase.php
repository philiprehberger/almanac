<?php

namespace Tests;

use App\Models\ApiKey;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** @return array{0: Workspace, 1: string} [workspace, admin-key plaintext] */
    protected function freshWorkspace(string $name = 'Test Workspace'): array
    {
        $workspace = Workspace::create(['name' => $name, 'slug' => str()->slug($name).'-'.uniqid()]);
        [, $plaintext] = ApiKey::mint($workspace, ApiKey::SCOPE_ADMIN_WRITE, 'test-admin');
        return [$workspace, $plaintext];
    }

    /** @return array<string, string> */
    protected function authed(string $key): array
    {
        return ['Authorization' => 'Bearer '.$key, 'Accept' => 'application/json'];
    }
}
