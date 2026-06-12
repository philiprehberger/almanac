<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasUlids, BelongsToWorkspace;

    protected $table = 'feedback';

    public $timestamps = false;

    public const VERDICT_UP = 'up';
    public const VERDICT_DOWN = 'down';

    protected $fillable = [
        'workspace_id',
        'query_id',
        'verdict',
        'comment',
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
}
