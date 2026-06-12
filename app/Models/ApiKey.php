<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasUlids, BelongsToWorkspace;

    public const SCOPE_CHAT_ONLY = 'chat_only';
    public const SCOPE_CHAT_FEEDBACK = 'chat_and_feedback';
    public const SCOPE_ADMIN_READ = 'admin_read';
    public const SCOPE_ADMIN_WRITE = 'admin_write';

    public const SCOPES = [
        self::SCOPE_CHAT_ONLY,
        self::SCOPE_CHAT_FEEDBACK,
        self::SCOPE_ADMIN_READ,
        self::SCOPE_ADMIN_WRITE,
    ];

    private const SCOPE_RANK = [
        self::SCOPE_CHAT_ONLY => 1,
        self::SCOPE_CHAT_FEEDBACK => 2,
        self::SCOPE_ADMIN_READ => 3,
        self::SCOPE_ADMIN_WRITE => 4,
    ];

    protected $fillable = [
        'workspace_id',
        'name',
        'prefix',
        'key_hash',
        'last_four',
        'scope',
        'ip_allowlist',
        'expires_at',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
            'ip_allowlist' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Mint a new key. Returns [model, plaintext]. Plaintext shown once.
     */
    public static function mint(
        Workspace $workspace,
        string $scope = self::SCOPE_CHAT_ONLY,
        ?string $name = null,
        ?array $ipAllowlist = null,
        ?\DateTimeInterface $expiresAt = null,
    ): array {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new \InvalidArgumentException("Invalid scope: {$scope}");
        }
        $prefix = 'alm_'.Str::random(8);
        $random = Str::random(32);
        $plaintext = $prefix.'_'.$random;

        $apiKey = static::withoutGlobalScope(WorkspaceScope::class)->create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => hash('sha256', $plaintext),
            'last_four' => substr($plaintext, -4),
            'scope' => $scope,
            'ip_allowlist' => $ipAllowlist,
            'expires_at' => $expiresAt,
        ]);

        return [$apiKey, $plaintext];
    }

    public static function findByPlaintext(?string $plaintext): ?self
    {
        if (! is_string($plaintext) || $plaintext === '') {
            return null;
        }
        return static::withoutGlobalScope(WorkspaceScope::class)
            ->whereNull('revoked_at')
            ->where('key_hash', hash('sha256', $plaintext))
            ->first();
    }

    public function hasScope(string $required): bool
    {
        $have = self::SCOPE_RANK[$this->scope] ?? 0;
        $need = self::SCOPE_RANK[$required] ?? 0;
        return $have >= $need;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function ipAllowed(string $ip): bool
    {
        $list = $this->ip_allowlist;
        if (! is_array($list) || $list === []) {
            return true;
        }
        foreach ($list as $cidr) {
            if (self::cidrContains((string) $cidr, $ip)) {
                return true;
            }
        }
        return false;
    }

    private static function cidrContains(string $cidr, string $ip): bool
    {
        if (! str_contains($cidr, '/')) {
            return inet_pton($cidr) === inet_pton($ip);
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $byteCount = intdiv($bits, 8);
        $remBits = $bits % 8;
        if (substr($ipBin, 0, $byteCount) !== substr($subnetBin, 0, $byteCount)) {
            return false;
        }
        if ($remBits === 0) {
            return true;
        }
        $mask = chr((0xFF << (8 - $remBits)) & 0xFF);
        return ($ipBin[$byteCount] & $mask) === ($subnetBin[$byteCount] & $mask);
    }
}
