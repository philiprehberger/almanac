<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasUlids, BelongsToWorkspace;

    public const EMBED_PENDING = 'pending';
    public const EMBED_EMBEDDED = 'embedded';
    public const EMBED_FAILED = 'failed';
    public const EMBED_UNSUPPORTED = 'unsupported';

    public const KIND_GDOC = 'gdoc';
    public const KIND_DOCX = 'docx';
    public const KIND_PDF = 'pdf';
    public const KIND_TXT = 'txt';
    public const KIND_MD = 'md';
    public const KIND_NOTION = 'notion_page';
    public const KIND_SLACK = 'slack_message';

    protected $fillable = [
        'workspace_id',
        'connector_id',
        'external_id',
        'title',
        'kind',
        'source_url',
        'etag',
        'modified_at',
        'embedded_at',
        'embed_status',
        'failure_reason',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'modified_at' => 'datetime',
            'embedded_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocChunk::class);
    }

    public function acls(): HasMany
    {
        return $this->hasMany(DocAcl::class);
    }
}
