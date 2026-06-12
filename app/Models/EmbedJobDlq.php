<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbedJobDlq extends Model
{
    use HasUlids, BelongsToWorkspace;

    protected $table = 'embed_jobs_dlq';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id',
        'document_id',
        'attempts',
        'last_error',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
