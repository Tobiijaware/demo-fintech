<?php

namespace Database\Seeders;

use App\Services\Kyc\TierCriteriaService;
use Illuminate\Database\Seeder;

class TierDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        app(TierCriteriaService::class)->syncFromConfig();
    }
}
