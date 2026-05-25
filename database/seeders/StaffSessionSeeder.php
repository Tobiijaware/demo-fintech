<?php

namespace Database\Seeders;

use App\Models\StaffSession;
use App\Models\User;
use App\Services\Governance\SessionService;
use Illuminate\Database\Seeder;

class StaffSessionSeeder extends Seeder
{
    public function run(): void
    {
        $sessionService = app(SessionService::class);

        User::query()
            ->whereNotNull('backoffice_role_id')
            ->limit(5)
            ->get()
            ->values()
            ->each(function (User $user, int $index) use ($sessionService) {
                $token = 'seed-session-'.$user->id;
                $session = $sessionService->registerSession(
                    $user,
                    $token,
                    '192.168.1.'.(10 + $index),
                    'DemoBrowser/1.0',
                );
                $session->update(['last_active_at' => now()->subMinutes($index * 3)]);
            });

        StaffSession::query()
            ->where('user_id', User::query()->where('email', 'admin@iwallet.demo')->value('id'))
            ->update(['last_active_at' => now()->subMinutes(2)]);
    }
}
