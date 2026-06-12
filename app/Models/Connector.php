<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Connector extends Model
{
    use HasUlids, BelongsToWorkspace;

    public const KIND_DRIVE = 'drive';
    public const KIND_NOTION = 'notion';
    public const KIND_SLACK = 'slack';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_REAUTH_REQUIRED = 'reauth_required';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'workspace_id',
        'kind',
        'label',
        'oauth_token',
        'config',
        'status',
        'backoff_until',
        'consecutive_failures',
        'paused_at',
        'last_sync_at',
    ];

    protected $hidden = ['oauth_token'];

    protected function casts(): array
    {
        return [
            'oauth_token' => 'encrypted',
            'config' => 'array',
            'backoff_until' => 'datetime',
            'paused_at' => 'datetime',
            'last_sync_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function ingestRuns(): HasMany
    {
        return $this->hasMany(IngestRun::class);
    }

    public function isInBackoff(): bool
    {
        return $this->backoff_until !== null && $this->backoff_until->isFuture();
    }

    public function nextBackoffSeconds(): int
    {
        $base = 60;
        $max = 6 * 3600;
        return min($max, $base * (2 ** min(10, $this->consecutive_failures)));
    }
}
