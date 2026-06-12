<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptInjectionSignal extends Model
{
    use HasUlids, BelongsToWorkspace;

    public $timestamps = false;

    public const SIGNAL_URL_OUTSIDE_SOURCES = 'url_outside_sources';
    public const SIGNAL_IMAGE_TAG = 'image_tag';
    public const SIGNAL_HALLUCINATED_CITATION = 'hallucinated_citation';
    public const SIGNAL_SCHEMA_VIOLATION = 'schema_violation';

    protected $fillable = [
        'workspace_id',
        'query_id',
        'signal_kind',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function parentQuery(): BelongsTo
    {
        return $this->belongsTo(Query::class, 'query_id');
    }
}
