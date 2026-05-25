<?php

namespace App\Models;

use App\Enums\SystemSettingGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
        'group',
        'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'group' => SystemSettingGroup::class,
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
