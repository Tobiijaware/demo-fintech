<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Regulator extends Model
{
    protected $fillable = [
        'code',
        'name',
        'status',
        'last_submission',
        'next_due',
        'contact_email',
        'filings_ytd',
    ];

    protected function casts(): array
    {
        return [
            'last_submission' => 'date',
            'next_due' => 'date',
            'filings_ytd' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
