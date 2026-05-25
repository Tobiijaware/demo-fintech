<?php

namespace Database\Seeders;

use App\Enums\SystemSettingGroup;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('system_settings.defaults', []) as $key => $meta) {
            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => ['value' => $meta['value']],
                    'group' => SystemSettingGroup::from($meta['group']),
                ],
            );
        }
    }
}
