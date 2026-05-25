<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmlCaseEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_id',
        'actor_id',
        'action',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function amlCase(): BelongsTo
    {
        return $this->belongsTo(AmlCase::class, 'case_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
