<?php

namespace App\Services\Governance;

use App\Enums\SystemSettingGroup;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SystemSettingsService
{
    public function __construct(private AuditLogService $auditLog) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getAllGrouped(): array
    {
        $settings = SystemSetting::query()->get()->keyBy('key');
        $grouped = [];

        foreach (SystemSettingGroup::cases() as $group) {
            $grouped[$group->value] = [];
        }

        foreach (config('system_settings.defaults', []) as $key => $meta) {
            $group = $meta['group'];
            $stored = $settings->get($key);
            $grouped[$group][$key] = $stored?->value['value'] ?? $meta['value'];
        }

        foreach ($settings as $key => $setting) {
            $group = $setting->group->value;
            if (! isset($grouped[$group])) {
                $grouped[$group] = [];
            }
            $grouped[$group][$key] = $setting->value['value'] ?? $setting->value;
        }

        return $grouped;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, array<string, mixed>>
     */
    public function updateBatch(User $actor, array $settings): array
    {
        $defaults = config('system_settings.defaults', []);
        $before = $this->getAllGrouped();
        $changed = [];

        foreach ($settings as $key => $value) {
            if (! isset($defaults[$key])) {
                throw new InvalidArgumentException("Unknown setting key: {$key}");
            }

            $meta = $defaults[$key];
            $group = SystemSettingGroup::from($meta['group']);

            SystemSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'value' => ['value' => $value],
                    'group' => $group,
                    'updated_by_id' => $actor->id,
                ],
            );

            $changed[$key] = $value;
        }

        $after = $this->getAllGrouped();

        if ($changed !== []) {
            $this->auditLog->record(
                $actor,
                'settings.updated',
                'SystemSetting',
                null,
                'Updated '.count($changed).' system setting(s)',
                ['before' => $before, 'after' => $after, 'changed' => $changed],
            );
        }

        return $after;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function integrationHealth(): array
    {
        $seeds = config('system_settings.integration_seeds', []);
        $results = [];

        foreach ($seeds as $id => $seed) {
            $results[] = array_merge(
                ['id' => $id],
                $seed,
                $this->probeIntegration($id, $seed),
            );
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array<string, mixed>
     */
    private function probeIntegration(string $id, array $seed): array
    {
        return match ($id) {
            'swwipe' => $this->probeHttp(
                config('swwipe.base_url'),
                config('swwipe.api_key') !== null && config('swwipe.api_key') !== '',
                $seed,
            ),
            'dojah' => $this->probeHttp(
                config('dojah.base_url'),
                config('dojah.app_id') && config('dojah.secret_key'),
                $seed,
            ),
            'email' => $this->probeEmail($seed),
            default => ['status' => $seed['status'] ?? 'unknown'],
        };
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array<string, mixed>
     */
    private function probeHttp(?string $baseUrl, bool $configured, array $seed): array
    {
        if (! $configured || ! $baseUrl) {
            return [
                'status' => 'degraded',
                'latency' => null,
                'configured' => false,
            ];
        }

        try {
            $start = microtime(true);
            $response = Http::timeout(3)->head(rtrim($baseUrl, '/'));
            $latencyMs = (int) round((microtime(true) - $start) * 1000);

            return [
                'status' => $response->successful() || $response->status() < 500 ? 'healthy' : 'degraded',
                'latency' => "{$latencyMs} ms",
                'configured' => true,
            ];
        } catch (\Throwable) {
            return [
                'status' => $seed['status'] ?? 'degraded',
                'latency' => $seed['latency'] ?? null,
                'configured' => true,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $seed
     * @return array<string, mixed>
     */
    private function probeEmail(array $seed): array
    {
        $mailer = config('mail.default');
        $configured = $mailer !== null && $mailer !== 'log';

        return [
            'status' => $configured ? ($seed['status'] ?? 'healthy') : 'degraded',
            'latency' => $seed['latency'] ?? null,
            'configured' => $configured,
        ];
    }
}
