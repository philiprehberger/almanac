<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IngestRun extends Model
{
    use HasUlids, BelongsToWorkspace;

    public $timestamps = false;

    public const MODE_INCREMENTAL = 'incremental';
    public const MODE_FULL = 'full';

    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workspace_id',
        'connector_id',
        'mode',
        'status',
        'docs_added',
        'docs_updated',
        'docs_removed',
        'docs_failed',
        'last_error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }
}
