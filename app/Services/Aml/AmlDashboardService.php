<?php

namespace App\Services\Aml;

use App\Enums\AmlAlertSeverity;
use App\Enums\AmlAlertStatus;
use App\Enums\AmlCaseStatus;
use App\Enums\SanctionHitStatus;
use App\Enums\StrFilingStatus;
use App\Models\AmlAlert;
use App\Models\AmlCase;
use App\Models\SanctionsHit;
use App\Models\StrFiling;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AmlDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $openAlerts = AmlAlert::query()
            ->whereIn('status', [AmlAlertStatus::Open, AmlAlertStatus::Assigned, AmlAlertStatus::Escalated]);

        $highSeverityOpen = (clone $openAlerts)->where('severity', AmlAlertSeverity::High)->count();

        $strsMtd = StrFiling::query()
            ->whereIn('status', [StrFilingStatus::Submitted, StrFilingStatus::Acknowledged])
            ->where('submitted_at', '>=', now()->startOfMonth())
            ->count();

        $strsPendingAck = StrFiling::query()
            ->where('status', StrFilingStatus::Submitted)
            ->whereNull('nfiu_reference')
            ->count();

        $sanctionHitsPending = SanctionsHit::query()
            ->where('status', SanctionHitStatus::PendingReview)
            ->count();

        $closedCases = AmlCase::query()
            ->where('status', AmlCaseStatus::Closed)
            ->whereNotNull('closed_at')
            ->whereNotNull('opened_at');

        $avgCloseHours = (clone $closedCases)
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, opened_at, closed_at)) as avg_hours')
            ->value('avg_hours');

        $avgCloseDays = $avgCloseHours !== null
            ? round(((float) $avgCloseHours) / 24, 1)
            : 0.0;

        return [
            'kpis' => [
                [
                    'label' => 'Open alerts',
                    'value' => (string) (clone $openAlerts)->count(),
                    'sub' => "{$highSeverityOpen} high severity",
                ],
                [
                    'label' => 'STRs filed (MTD)',
                    'value' => (string) $strsMtd,
                    'sub' => "{$strsPendingAck} pending NFIU acknowledgement",
                ],
                [
                    'label' => 'Sanction hits',
                    'value' => (string) SanctionsHit::query()->count(),
                    'sub' => $sanctionHitsPending > 0
                        ? "{$sanctionHitsPending} pending review"
                        : 'All under review',
                ],
                [
                    'label' => 'Avg. close time',
                    'value' => $avgCloseDays > 0 ? "{$avgCloseDays}d" : '—',
                    'sub' => $avgCloseDays > 0 && $avgCloseDays < 5 ? 'Below 5d target' : 'Target: 5d',
                ],
            ],
            'typology_breakdown' => $this->typologyBreakdown(),
            'weekly_alert_counts' => $this->weeklyAlertCounts(),
        ];
    }

    /**
     * @return list<array{label: string, count: int}>
     */
    private function typologyBreakdown(): array
    {
        return AmlAlert::query()
            ->whereIn('status', [AmlAlertStatus::Open, AmlAlertStatus::Assigned, AmlAlertStatus::Escalated])
            ->select('typology', DB::raw('COUNT(*) as count'))
            ->groupBy('typology')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'label' => $row->typology ?? 'Other',
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, value: int, day: string}>
     */
    private function weeklyAlertCounts(): array
    {
        $start = now()->startOfWeek(Carbon::MONDAY);
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $counts = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $counts[] = [
                'label' => $days[$i],
                'day' => $day->toDateString(),
                'value' => AmlAlert::query()->whereDate('created_at', $day)->count(),
            ];
        }

        return $counts;
    }
}
