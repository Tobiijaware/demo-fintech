<?php

namespace Database\Seeders;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Models\OperationsIncident;
use App\Models\OperationsIncidentEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        $declaredBy = User::query()->where('email', 'operations@iwallet.demo')->first();

        $incidents = [
            [
                'reference' => 'INC-2026-04881',
                'title' => 'GTBank NIP outbound failing',
                'summary' => 'NIP outbound transfers to GTBank are failing at 23% rate. First detected by automated monitoring at 08:58 WAT. Affecting approximately 1,400 transactions per hour. Partner bank notified at 09:02; awaiting their RCA. Affected customers receive standard NIP timeout message — funds remain in customer wallet.',
                'severity' => IncidentSeverity::P1,
                'status' => IncidentStatus::Active,
                'owner_name' => 'Engineering',
                'owner_role' => 'J. Adekunle',
                'impact' => [
                    'failed_transactions' => '428 (so far)',
                    'customers_affected' => '~380',
                    'volume_at_risk' => '₦18.4M (queued)',
                    'channels_impacted' => 'All NIP-to-GTBank',
                ],
                'started_at' => now()->setTime(8, 58),
                'events' => [
                    ['actor_name' => 'System · NIP/GTBank route monitor', 'action' => 'Automated alert · success rate dropped below 80%', 'offset_minutes' => 0],
                    ['actor_name' => 'Sade Bankole · ops lead', 'action' => 'P1 declared · war room opened', 'offset_minutes' => 4],
                    ['actor_name' => 'Engineering · J. Adekunle', 'action' => 'GTBank technical contact notified', 'offset_minutes' => 6],
                    ['actor_name' => 'Support team · standard template T-NIP-FAIL', 'action' => 'Customer comms drafted', 'offset_minutes' => 10],
                    ['actor_name' => 'Vendor', 'action' => 'GTBank acknowledged · investigating switch latency', 'offset_minutes' => 14],
                ],
            ],
            [
                'reference' => 'INC-2026-04872',
                'title' => 'POS terminal sync delays',
                'summary' => 'Terminal configuration sync to 3,200 POS devices is delayed by 45–90 minutes. Transactions still processing but end-of-day reconciliation may slip.',
                'severity' => IncidentSeverity::P2,
                'status' => IncidentStatus::Active,
                'owner_name' => 'Field ops',
                'owner_role' => 'Regional team',
                'impact' => [
                    'failed_transactions' => '12 (timeouts)',
                    'customers_affected' => '~2,100 merchants',
                    'volume_at_risk' => '₦4.2M (pending sync)',
                    'channels_impacted' => 'POS network',
                ],
                'started_at' => now()->setTime(8, 14),
                'events' => [
                    ['actor_name' => 'System · POS monitor', 'action' => 'Sync lag detected on Lagos cluster', 'offset_minutes' => 0],
                    ['actor_name' => 'Sade Bankole · ops lead', 'action' => 'Field ops dispatched to hub sites', 'offset_minutes' => 16],
                ],
            ],
            [
                'reference' => 'INC-2026-04865',
                'title' => 'SMS gateway · delivery slow',
                'summary' => 'OTP and transaction alert SMS delivery on MTN route showing 8–12 min delays. Fallback route active for critical alerts.',
                'severity' => IncidentSeverity::P3,
                'status' => IncidentStatus::Monitoring,
                'owner_name' => 'Vendor',
                'owner_role' => 'Infobip',
                'impact' => [
                    'failed_transactions' => '0',
                    'customers_affected' => '~1,200 (delayed OTP)',
                    'volume_at_risk' => '—',
                    'channels_impacted' => 'SMS / OTP',
                ],
                'started_at' => now()->setTime(7, 42),
                'events' => [
                    ['actor_name' => 'System · SMS gateway monitor', 'action' => 'Delivery latency spike detected', 'offset_minutes' => 0],
                    ['actor_name' => 'Engineering', 'action' => 'Vendor ticket opened', 'offset_minutes' => 18],
                ],
            ],
        ];

        foreach ($incidents as $row) {
            $events = $row['events'];
            unset($row['events']);

            $incident = OperationsIncident::query()->updateOrCreate(
                ['reference' => $row['reference']],
                array_merge($row, [
                    'declared_by_id' => $declaredBy?->id,
                ]),
            );

            foreach ($events as $event) {
                $createdAt = Carbon::parse($incident->started_at)->addMinutes($event['offset_minutes']);

                OperationsIncidentEvent::query()->updateOrCreate(
                    [
                        'incident_id' => $incident->id,
                        'action' => $event['action'],
                        'actor_name' => $event['actor_name'],
                    ],
                    ['created_at' => $createdAt],
                );
            }
        }
    }
}
