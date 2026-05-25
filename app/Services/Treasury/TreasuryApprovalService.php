<?php

namespace App\Services\Treasury;

use App\Models\ApprovalRequest;
use App\Models\FloatPosition;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Governance\ApprovalRequestService;
use InvalidArgumentException;

class TreasuryApprovalService
{
    public function __construct(
        private ApprovalRequestService $approvalRequests,
        private TreasuryService $treasury,
        private AuditLogService $auditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitFloatTopUp(FloatPosition $position, User $maker, array $data): ApprovalRequest
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Top-up amount must be greater than zero.');
        }

        $notes = $data['notes'] ?? null;

        $request = $this->approvalRequests->submit(
            $maker,
            'float_positions',
            'float_position',
            (string) $position->id,
            [
                'action' => 'float_top_up',
                'type' => 'top_up',
                'float_position_id' => $position->id,
                'amount' => $amount,
                'notes' => $notes,
                'bank_name' => $position->bank_name,
                'account_number' => $position->account_number,
            ],
            "Float top-up ₦".number_format($amount, 2)." — {$position->bank_name}",
        );

        $this->auditLog->record(
            $maker,
            'treasury.float_top_up_submitted',
            'float_position',
            (string) $position->id,
            "Submitted float top-up approval for {$position->bank_name}",
            ['approval_request_id' => $request->id, 'amount' => $amount],
        );

        return $request->load(['policy', 'maker.backofficeRole']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitFloatPayout(FloatPosition $position, User $maker, array $data): ApprovalRequest
    {
        $amount = (float) ($data['amount'] ?? 0);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payout amount must be greater than zero.');
        }

        if ((float) $position->balance < $amount) {
            throw new InvalidArgumentException('Insufficient float balance for payout request.');
        }

        $notes = $data['notes'] ?? null;

        $request = $this->approvalRequests->submit(
            $maker,
            'float_positions',
            'float_position',
            (string) $position->id,
            [
                'action' => 'float_payout',
                'type' => 'payout',
                'float_position_id' => $position->id,
                'amount' => $amount,
                'notes' => $notes,
                'bank_name' => $position->bank_name,
                'account_number' => $position->account_number,
            ],
            "Float payout ₦".number_format($amount, 2)." — {$position->bank_name}",
        );

        $this->auditLog->record(
            $maker,
            'treasury.float_payout_submitted',
            'float_position',
            (string) $position->id,
            "Submitted float payout approval for {$position->bank_name}",
            ['approval_request_id' => $request->id, 'amount' => $amount],
        );

        return $request->load(['policy', 'maker.backofficeRole']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function submitCommissionBatch(User $maker, array $data): ApprovalRequest
    {
        $period = $data['period'] ?? now()->format('Y-m');
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException('Period must be in YYYY-MM format.');
        }

        $summary = $this->treasury->commissionBatches(['period' => $period]);
        $batch = $summary['items'][0] ?? null;

        if (! $batch || $batch['amount'] <= 0) {
            throw new InvalidArgumentException('No commission batch available for the requested period.');
        }

        $batchId = $batch['batch_id'];

        $request = $this->approvalRequests->submit(
            $maker,
            'commission_payout',
            'commission_batch',
            $batchId,
            [
                'action' => 'commission_batch_payout',
                'type' => 'commission_batch',
                'batch_id' => $batchId,
                'period' => $period,
                'amount' => $batch['amount'],
                'agents' => $batch['agents'],
            ],
            "Commission batch payout {$batchId} — ₦".number_format($batch['amount'], 2),
        );

        $this->auditLog->record(
            $maker,
            'treasury.commission_batch_submitted',
            'commission_batch',
            $batchId,
            "Submitted commission batch approval for {$batchId}",
            ['approval_request_id' => $request->id, 'amount' => $batch['amount']],
        );

        return $request->load(['policy', 'maker.backofficeRole']);
    }

    public function approve(ApprovalRequest $request, User $checker): ApprovalRequest
    {
        $updated = $this->approvalRequests->approve($request, $checker);

        $this->auditLog->record(
            $checker,
            'treasury.approval_approved',
            'approval_request',
            (string) $updated->id,
            "Approved treasury request #{$updated->id}",
            ['payload' => $updated->payload],
        );

        return $updated;
    }

    public function reject(ApprovalRequest $request, User $checker, string $reason): ApprovalRequest
    {
        $updated = $this->approvalRequests->reject($request, $checker, $reason);

        $this->auditLog->record(
            $checker,
            'treasury.approval_rejected',
            'approval_request',
            (string) $updated->id,
            "Rejected treasury request #{$updated->id}",
            ['reason' => $reason],
        );

        return $updated;
    }
}
