<?php

namespace App\Services\Kyc;

use App\Enums\KycLevel;
use App\Enums\KycStatus;
use App\Models\KycVerification;
use App\Models\User;
use Illuminate\Support\Collection;

class KycService
{
    public function initializeForUser(User $user): Collection
    {
        $levels = [KycLevel::IdentityVerification, KycLevel::ProofOfAddress];

        return collect($levels)->map(function (KycLevel $level) use ($user) {
            return KycVerification::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'level' => $level,
                ],
                [
                    'status' => KycStatus::Pending,
                ]
            );
        });
    }
}
