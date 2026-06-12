<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityMapping extends Model
{
    use HasUlids, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'almanac_user_id',
        'source_kind',
        'source_principal_id',
        'source_principal_kind',
        'refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'refreshed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'almanac_user_id');
    }
}
