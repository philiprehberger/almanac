<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GapCluster extends Model
{
    use HasUlids, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'centroid_text',
        'member_count',
        'addressed_at',
        'last_recomputed_at',
    ];

    protected function casts(): array
    {
        return [
            'addressed_at' => 'datetime',
            'last_recomputed_at' => 'datetime',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(UnansweredQuestion::class, 'cluster_id');
    }
}
