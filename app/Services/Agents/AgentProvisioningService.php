<?php

namespace App\Services\Agents;

use App\Enums\AgentStatus;
use App\Enums\ApplicantType;
use App\Models\Agent;
use App\Models\OnboardingApplication;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use InvalidArgumentException;

class AgentProvisioningService
{
    public function __construct(
        private AuditLogService $auditLog,
    ) {}

    public function provisionFromOnboarding(OnboardingApplication $app, ?User $actor = null): Agent
    {
        if ($app->applicant_type !== ApplicantType::Agent) {
            throw new InvalidArgumentException('Only agent onboarding applications can be provisioned.');
        }

        $existing = Agent::query()
            ->where('onboarding_application_id', $app->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $agent = Agent::query()->create([
            'code' => $this->generateCode(),
            'onboarding_application_id' => $app->id,
            'business_name' => $app->business_name ?? 'Unnamed agent',
            'proprietor_name' => $app->proprietor_name ?? 'Unknown',
            'location' => $app->location,
            'cac_number' => $app->cac_number,
            'tier' => $app->tier,
            'status' => AgentStatus::Active,
            'region' => $this->resolveRegion($app),
            'hub' => null,
            'user_id' => $app->user_id,
            'float_balance' => 0,
            'metadata' => [
                'onboarding_reference' => $app->reference,
                'channel' => $app->channel->value,
                'provisioned_at' => now()->toIso8601String(),
            ],
        ]);

        $this->auditLog->record(
            $actor,
            'agent.created',
            'Agent',
            (string) $agent->id,
            "Provisioned agent {$agent->code} from onboarding {$app->reference}",
            [
                'code' => $agent->code,
                'onboarding_application_id' => $app->id,
                'onboarding_reference' => $app->reference,
                'tier' => $agent->tier->value,
            ],
        );

        return $agent;
    }

    protected function generateCode(): string
    {
        $latest = Agent::query()
            ->where('code', 'like', 'AGT-%')
            ->orderByDesc('id')
            ->value('code');

        $sequence = 1;

        if ($latest && preg_match('/^AGT-(\d+)$/', $latest, $matches)) {
            $sequence = (int) $matches[1] + 1;
        }

        return 'AGT-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
    }

    protected function resolveRegion(OnboardingApplication $app): ?string
    {
        $location = $app->location;

        if (! $location) {
            return null;
        }

        $parts = preg_split('/[,\/]/', $location);

        return trim($parts[0] ?? $location) ?: null;
    }
}
