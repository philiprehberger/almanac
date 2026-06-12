<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class DeletionRequest extends Model
{
    use HasUlids, BelongsToWorkspace;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_FAILED = 'failed';

    public const SCOPE_QUERIES = 'queries';
    public const SCOPE_ALL = 'all';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'subject_user_external_id',
        'scope',
        'status',
        'affected_query_ids',
        'last_error',
        'created_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'affected_query_ids' => 'array',
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
