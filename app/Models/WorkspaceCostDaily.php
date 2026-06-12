<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceCostDaily extends Model
{
    protected $table = 'workspace_cost_daily';

    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'workspace_id',
        'day',
        'cost_usd',
        'query_count',
        'tokens_in',
        'tokens_out',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'cost_usd' => 'decimal:6',
        ];
    }
}
