<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocChunk extends Model
{
    use HasUlids, BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'document_id',
        'seq',
        'text',
        'embedding',
        'token_count',
        'chunker_version',
        'embedder_version',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
