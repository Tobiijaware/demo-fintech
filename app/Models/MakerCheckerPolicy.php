<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MakerCheckerPolicy extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'department',
        'action',
        'description',
        'resource',
        'threshold',
        'enforced',
        'enforcement',
        'role_pairs',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'enforced' => 'boolean',
            'role_pairs' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
