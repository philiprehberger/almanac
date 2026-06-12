<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocAcl extends Model
{
    use HasUlids, BelongsToWorkspace;

    public const PRINCIPAL_USER = 'user';
    public const PRINCIPAL_GROUP = 'group';
    public const PRINCIPAL_WORKSPACE = 'workspace';
    public const PRINCIPAL_PUBLIC = 'public';

    protected $table = 'doc_acls';

    protected $fillable = [
        'workspace_id',
        'document_id',
        'principal_kind',
        'principal_external_id',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
