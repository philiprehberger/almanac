<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasUlids;

    protected $fillable = [
        'name',
        'slug',
        'monthly_budget_usd',
        'allowed_chat_origins',
        'demo_principals',
        'degraded_until',
    ];

    protected function casts(): array
    {
        return [
            'monthly_budget_usd' => 'decimal:2',
            'allowed_chat_origins' => 'array',
            'demo_principals' => 'array',
            'degraded_until' => 'datetime',
        ];
    }

    /**
     * Whether a raw synthetic principal (asserted via `as_principal`) is on
     * this workspace's demo allowlist. Fail-closed: no allowlist → no match.
     *
     * @param  array{kind?:mixed, id?:mixed}  $principal
     */
    public function allowsSyntheticPrincipal(array $principal): bool
    {
        $allow = $this->demo_principals;
        if (! is_array($allow) || $allow === []) {
            return false;
        }
        $kind = (string) ($principal['kind'] ?? '');
        $id = (string) ($principal['id'] ?? '');
        foreach ($allow as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            if ((string) ($entry['kind'] ?? '') === $kind && (string) ($entry['id'] ?? '') === $id) {
                return true;
            }
        }
        return false;
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    public function connectors(): HasMany
    {
        return $this->hasMany(Connector::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class);
    }

    public function unansweredQuestions(): HasMany
    {
        return $this->hasMany(UnansweredQuestion::class);
    }

    public function gapClusters(): HasMany
    {
        return $this->hasMany(GapCluster::class);
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    public function isDegraded(): bool
    {
        return $this->degraded_until !== null && $this->degraded_until->isFuture();
    }
}
