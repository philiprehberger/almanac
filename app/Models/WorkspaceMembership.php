<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMembership extends Model
{
    use HasUlids;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_VIEWER = 'viewer';

    public const CAP_PROMPT_EDIT = 'prompt_edit';
    public const CAP_AUDIT_READ = 'audit_read';
    public const CAP_COST_ADMIN = 'cost_admin';

    protected $fillable = ['workspace_id', 'user_id', 'role', 'capabilities'];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasCapability(string $capability): bool
    {
        if ($this->role === self::ROLE_ADMIN) {
            return true;
        }
        $caps = $this->capabilities ?? [];
        return in_array($capability, $caps, true);
    }
}
