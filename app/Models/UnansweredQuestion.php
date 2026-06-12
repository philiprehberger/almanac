<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnansweredQuestion extends Model
{
    use HasUlids, BelongsToWorkspace;

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'query_id',
        'reason',
        'cluster_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function parentQuery(): BelongsTo
    {
        return $this->belongsTo(Query::class, 'query_id');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(GapCluster::class, 'cluster_id');
    }
}
