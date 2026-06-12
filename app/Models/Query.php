<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Query extends Model
{
    use HasUlids, BelongsToWorkspace;

    public $timestamps = false;

    public const CONFIDENCE_LOW = 'low';
    public const CONFIDENCE_HIGH = 'high';

    public const REASON_MODEL_UNSURE = 'model_unsure';
    public const REASON_SCORE_THIN = 'score_thin';
    public const REASON_ACL_THIN = 'acl_thin';

    protected $fillable = [
        'workspace_id',
        'conversation_id',
        'query_text',
        'principal_set',
        'retrieved_chunk_ids',
        'model',
        'answer_text',
        'citations',
        'confidence',
        'confidence_reason',
        'latency_ms',
        'tokens_in',
        'tokens_out',
        'cost_usd',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'principal_set' => 'array',
            'retrieved_chunk_ids' => 'array',
            'citations' => 'array',
            'cost_usd' => 'decimal:6',
            'created_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    public function unanswered(): HasOne
    {
        return $this->hasOne(UnansweredQuestion::class);
    }
}
