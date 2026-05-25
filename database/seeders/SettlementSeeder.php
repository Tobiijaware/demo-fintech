<?php

namespace Database\Seeders;

use App\Enums\SettlementCycleStatus;
use App\Enums\SettlementExceptionCategory;
use App\Enums\SettlementExceptionStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\PartnerBank;
use App\Models\SettlementCycle;
use App\Models\SettlementException;
use App\Models\SettlementExceptionEvent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class SettlementSeeder extends Seeder
{
    public function run(): void
    {
        $settlement = User::query()->where('email', 'settlement@iwallet.demo')->first();
        $david = User::query()->where('email', 'david.adeyemi@demo.ng')->with('wallet')->first();
        $james = User::query()->where('email', 'james.obi@demo.ng')->with('wallet')->first();

        $this->seedPartnerBanks();
        $this->seedNipTransactions($david, $james);

        $cycle1 = SettlementCycle::query()->updateOrCreate(
            ['reference' => 'CYC-2026-001'],
            [
                'label' => 'Cycle 1 · 08:00',
                'scheduled_at' => now()->startOfDay()->setTime(8, 0),
                'settled_at' => now()->startOfDay()->setTime(9, 15),
                'amount' => 1_840_000_000,
                'txn_count' => 87219,
                'channel' => 'NIBSS',
                'status' => SettlementCycleStatus::Settled,
                'metadata' => ['detail' => '87,219 txns · NIBSS'],
            ],
        );

        $cycle2 = SettlementCycle::query()->updateOrCreate(
            ['reference' => 'CYC-2026-002'],
            [
                'label' => 'Cycle 2 · 12:00',
                'scheduled_at' => now()->startOfDay()->setTime(12, 0),
                'amount' => 2_110_000_000,
                'txn_count' => 98403,
                'channel' => 'NIBSS',
                'status' => SettlementCycleStatus::InProgress,
                'metadata' => ['detail' => '98,403 txns · NIBSS'],
            ],
        );

        SettlementCycle::query()->updateOrCreate(
            ['reference' => 'CYC-2026-003'],
            [
                'label' => 'Cycle 3 · 17:00',
                'scheduled_at' => now()->startOfDay()->setTime(17, 0),
                'amount' => 870_000_000,
                'txn_count' => 32784,
                'channel' => 'NIBSS',
                'status' => SettlementCycleStatus::Scheduled,
                'metadata' => [
                    'detail' => '32,784 txns (est.)',
                    'estimated_amount' => 870_000_000,
                    'estimated_txn_count' => 32784,
                ],
            ],
        );

        Transaction::query()->updateOrCreate(
            ['reference' => 'TXN20260525001'],
            [
                'session_id' => 'NIP-2818392',
                'user_id' => $david?->id,
                'wallet_id' => $david?->wallet?->id,
                'type' => TransactionType::WalletTransferIn,
                'direction' => TransactionDirection::Credit,
                'amount' => 450_000,
                'status' => TransactionStatus::Failed,
                'counterparty_name' => 'GTBank',
                'counterparty_account' => '0123847291',
                'counterparty_bank' => 'GTBank',
                'narrative' => 'NIP credit from GTBank — wallet posting missing',
                'created_at' => now()->setTime(8, 54, 22),
            ],
        );

        Transaction::query()->updateOrCreate(
            ['reference' => 'TXN20260525002'],
            [
                'session_id' => 'NIP-2818455',
                'user_id' => $james?->id,
                'wallet_id' => $james?->wallet?->id,
                'type' => TransactionType::WalletTransferIn,
                'direction' => TransactionDirection::Credit,
                'amount' => 120_000,
                'status' => TransactionStatus::Success,
                'counterparty_name' => 'Access Bank',
                'counterparty_account' => '0129921441',
                'counterparty_bank' => 'Access Bank',
                'narrative' => 'NIP credit from Access Bank',
                'created_at' => now()->setTime(8, 22, 10),
            ],
        );

        Transaction::query()->updateOrCreate(
            ['reference' => 'TXN20260525003'],
            [
                'session_id' => 'NIP-2818601',
                'user_id' => $david?->id,
                'wallet_id' => $david?->wallet?->id,
                'type' => TransactionType::WalletTransferOut,
                'direction' => TransactionDirection::Debit,
                'amount' => 85_000,
                'status' => TransactionStatus::Pending,
                'counterparty_name' => 'UBA',
                'counterparty_account' => '0127721009',
                'counterparty_bank' => 'UBA',
                'narrative' => 'NIP transfer to UBA — chargeback pending',
                'created_at' => now()->setTime(7, 30, 15),
            ],
        );

        $exceptions = [
            [
                'reference' => 'EXC-2026-001',
                'cycle_id' => $cycle2->id,
                'category' => SettlementExceptionCategory::FailedCredit,
                'status' => SettlementExceptionStatus::InInvestigation,
                'title' => 'GTBank → Wallet · failed credit',
                'summary' => 'NIP transfer from GTBank to customer wallet W-'.($david?->wallet?->id ?? '44218').'. ₦450,000 debited from GTBank settlement account, but customer wallet was not credited. Switch returned successful response; wallet service shows no incoming credit. Discrepancy detected during 09:00 reconciliation run.',
                'amount' => 450_000,
                'transaction_reference' => 'NIP-2818392',
                'trace' => [
                    'nipReference' => 'NIP-2818392',
                    'amount' => '₦450,000',
                    'sender' => 'GTBank · 012-3847-2918',
                    'beneficiary' => 'Wallet W-'.($david?->wallet?->id ?? '44218'),
                    'switchTime' => '08:54:22 WAT',
                    'switchStatus' => 'Success',
                    'walletPosting' => 'Missing',
                    'nibssConfirmation' => 'Pending',
                ],
                'recommended_action' => 'Action: Manual credit to wallet W-'.($david?->wallet?->id ?? '44218').'. Switch confirmed success; funds were settled in our GTBank account. Wallet credit was lost in inter-service messaging. Approval threshold: settlement officer can post manual credit up to ₦1M; this case (₦450k) auto-creates GL journal entry.',
                'events' => [
                    ['action' => 'NIP credit instruction received', 'notes' => 'From GTBank · ₦450k', 'offset_minutes' => -6],
                    ['action' => 'Switch returned success', 'notes' => 'Reference NIP-2818392', 'offset_minutes' => -6],
                    ['action' => 'Reconciliation found mismatch', 'notes' => 'System · auto-flagged', 'offset_minutes' => 0],
                    ['action' => 'Assigned to settlement officer', 'notes' => 'Ngozi Okeke', 'offset_minutes' => 8],
                ],
            ],
            [
                'reference' => 'EXC-2026-002',
                'cycle_id' => $cycle2->id,
                'category' => SettlementExceptionCategory::Duplicate,
                'status' => SettlementExceptionStatus::Open,
                'title' => 'Duplicate credit · Access Bank',
                'summary' => 'Access Bank NIP credit NIP-2818455 posted twice to wallet W-'.($james?->wallet?->id ?? '22901').' within the same reconciliation window.',
                'amount' => 120_000,
                'transaction_reference' => 'NIP-2818455',
                'trace' => [
                    'nipReference' => 'NIP-2818455',
                    'amount' => '₦120,000',
                    'sender' => 'Access Bank · 012-9921-4410',
                    'beneficiary' => 'Wallet W-'.($james?->wallet?->id ?? '22901'),
                    'switchTime' => '08:22:10 WAT',
                    'switchStatus' => 'Success',
                    'walletPosting' => 'Duplicate',
                    'nibssConfirmation' => 'Confirmed',
                ],
                'recommended_action' => 'Action: Reverse duplicate posting and retain single credit. Requires partner confirmation before GL reversal.',
                'events' => [
                    ['action' => 'First credit posted', 'notes' => 'Wallet service', 'offset_minutes' => -38],
                    ['action' => 'Duplicate credit detected', 'notes' => 'Reconciliation engine', 'offset_minutes' => -38],
                ],
            ],
            [
                'reference' => 'EXC-2026-003',
                'cycle_id' => $cycle1->id,
                'category' => SettlementExceptionCategory::Unmatched,
                'status' => SettlementExceptionStatus::Open,
                'title' => 'Agent commission · unmatched',
                'summary' => 'Commission batch PSB-9920178 settled at switch but no matching GL journal was created.',
                'amount' => 8_400,
                'transaction_reference' => 'PSB-9920178',
                'trace' => [
                    'nipReference' => 'PSB-9920178',
                    'amount' => '₦8,400',
                    'sender' => 'Internal · Commission batch',
                    'beneficiary' => 'Agent AGT-44102',
                    'switchTime' => '07:48:00 WAT',
                    'switchStatus' => 'Success',
                    'walletPosting' => 'Posted',
                    'nibssConfirmation' => 'N/A',
                ],
                'recommended_action' => 'Action: Post manual GL entry against agent commission suspense account.',
                'events' => [
                    ['action' => 'Commission batch settled', 'notes' => 'Switch', 'offset_minutes' => -72],
                    ['action' => 'GL mismatch flagged', 'notes' => 'Reconciliation', 'offset_minutes' => 1],
                ],
            ],
            [
                'reference' => 'EXC-2026-004',
                'cycle_id' => $cycle2->id,
                'category' => SettlementExceptionCategory::Pending,
                'status' => SettlementExceptionStatus::PendingPartner,
                'title' => 'Reversal pending · UBA',
                'summary' => 'Customer chargeback initiated for UBA outbound transfer. Awaiting partner bank reversal confirmation.',
                'amount' => 85_000,
                'transaction_reference' => 'NIP-2818601',
                'trace' => [
                    'nipReference' => 'NIP-2818601',
                    'amount' => '₦85,000',
                    'sender' => 'Wallet W-'.($david?->wallet?->id ?? '88201'),
                    'beneficiary' => 'UBA · 012-7721-0098',
                    'switchTime' => '07:30:15 WAT',
                    'switchStatus' => 'Pending',
                    'walletPosting' => 'Debited',
                    'nibssConfirmation' => 'Awaiting',
                ],
                'recommended_action' => 'Action: Hold wallet debit until UBA confirms reversal status.',
                'events' => [
                    ['action' => 'Chargeback request logged', 'notes' => 'Customer support', 'offset_minutes' => -90],
                    ['action' => 'Sent to UBA ops', 'notes' => 'Settlement team', 'offset_minutes' => -45],
                ],
            ],
            [
                'reference' => 'EXC-2026-005',
                'cycle_id' => $cycle2->id,
                'category' => SettlementExceptionCategory::Unmatched,
                'status' => SettlementExceptionStatus::InInvestigation,
                'title' => 'Zenith inbound · no match',
                'summary' => 'Inbound NIP credit from Zenith with no matching customer-initiated transaction in core ledger.',
                'amount' => 240_000,
                'transaction_reference' => 'NIP-2818721',
                'trace' => [
                    'nipReference' => 'NIP-2818721',
                    'amount' => '₦240,000',
                    'sender' => 'Zenith · 012-4410-8821',
                    'beneficiary' => 'Settlement account',
                    'switchTime' => '07:12:44 WAT',
                    'switchStatus' => 'Success',
                    'walletPosting' => 'Unmatched',
                    'nibssConfirmation' => 'Confirmed',
                ],
                'recommended_action' => 'Action: Trace source account and either match to suspense or initiate return transfer.',
                'events' => [
                    ['action' => 'Inbound credit received', 'notes' => 'NIBSS feed', 'offset_minutes' => -108],
                    ['action' => 'No source txn found', 'notes' => 'Reconciliation', 'offset_minutes' => 0],
                ],
            ],
            [
                'reference' => 'EXC-2026-006',
                'cycle_id' => $cycle1->id,
                'category' => SettlementExceptionCategory::FailedCredit,
                'status' => SettlementExceptionStatus::Open,
                'title' => 'Failed beneficiary lookup · FCMB',
                'summary' => 'NIP transfer failed at beneficiary lookup — FCMB account invalid. Funds not debited from settlement float.',
                'amount' => 67_000,
                'transaction_reference' => 'NIP-2818802',
                'trace' => [
                    'nipReference' => 'NIP-2818802',
                    'amount' => '₦67,000',
                    'sender' => 'Wallet W-33102',
                    'beneficiary' => 'FCMB · invalid account',
                    'switchTime' => '06:58:02 WAT',
                    'switchStatus' => 'Failed',
                    'walletPosting' => 'Not attempted',
                    'nibssConfirmation' => 'Rejected',
                ],
                'recommended_action' => 'Action: Notify customer and close exception — no wallet action required.',
                'events' => [
                    ['action' => 'Beneficiary lookup failed', 'notes' => 'NIBSS', 'offset_minutes' => -122],
                ],
            ],
        ];

        foreach ($exceptions as $row) {
            $events = $row['events'];
            unset($row['events']);

            $exception = SettlementException::query()->updateOrCreate(
                ['reference' => $row['reference']],
                $row,
            );

            foreach ($events as $event) {
                SettlementExceptionEvent::query()->updateOrCreate(
                    [
                        'exception_id' => $exception->id,
                        'action' => $event['action'],
                    ],
                    [
                        'actor_id' => $settlement?->id,
                        'notes' => $event['notes'],
                        'created_at' => now()->addMinutes($event['offset_minutes']),
                    ],
                );
            }
        }
    }

    private function seedPartnerBanks(): void
    {
        $banks = [
            [
                'name' => 'GTBank',
                'account_number' => '012-3847-2918',
                'settlement_window' => 'T+0 · 08:00 / 12:00 / 17:00',
                'sla_status' => 'degraded',
                'failure_rate_24h' => 4.2,
                'metadata' => ['aliases' => ['GTBank', 'Guaranty Trust Bank']],
            ],
            [
                'name' => 'Access Bank',
                'account_number' => '001-8821-4402',
                'settlement_window' => 'T+0 · 08:00 / 12:00 / 17:00',
                'sla_status' => 'healthy',
                'failure_rate_24h' => 0.8,
                'metadata' => ['aliases' => ['Access Bank']],
            ],
            [
                'name' => 'Zenith Bank',
                'account_number' => '057-2918-4827',
                'settlement_window' => 'T+0 · 08:00 / 12:00 / 17:00',
                'sla_status' => 'healthy',
                'failure_rate_24h' => 1.1,
                'metadata' => ['aliases' => ['Zenith Bank', 'Zenith']],
            ],
            [
                'name' => 'UBA',
                'account_number' => '209-1102-8831',
                'settlement_window' => 'T+0 · 08:00 / 12:00 / 17:00',
                'sla_status' => 'healthy',
                'failure_rate_24h' => 1.4,
                'metadata' => ['aliases' => ['UBA', 'United Bank for Africa']],
            ],
            [
                'name' => 'FCMB',
                'account_number' => '304-9921-1180',
                'settlement_window' => 'T+0 · 08:00 / 12:00 / 17:00',
                'sla_status' => 'warning',
                'failure_rate_24h' => 2.6,
                'metadata' => ['aliases' => ['FCMB', 'First City Monument Bank']],
            ],
            [
                'name' => 'First Bank',
                'account_number' => '304-9921-1180',
                'settlement_window' => 'T+1 · 09:00',
                'sla_status' => 'healthy',
                'failure_rate_24h' => 0.9,
                'metadata' => ['aliases' => ['First Bank', 'First Bank of Nigeria']],
            ],
        ];

        foreach ($banks as $bank) {
            PartnerBank::query()->updateOrCreate(
                ['name' => $bank['name']],
                $bank,
            );
        }
    }

    private function seedNipTransactions(?User $david, ?User $james): void
    {
        if (! $david || ! $james) {
            return;
        }

        Transaction::query()->updateOrCreate(
            ['reference' => 'TXN20260525004'],
            [
                'session_id' => 'NIP-2818721',
                'user_id' => $david->id,
                'wallet_id' => $david->wallet?->id,
                'type' => TransactionType::WalletTransferIn,
                'direction' => TransactionDirection::Credit,
                'amount' => 240_000,
                'status' => TransactionStatus::Success,
                'counterparty_name' => 'Zenith Bank',
                'counterparty_account' => '0124410882',
                'counterparty_bank' => 'Zenith Bank',
                'narrative' => 'Inbound NIP from Zenith — unmatched source',
                'created_at' => now()->subHours(2),
            ],
        );

        Transaction::query()->updateOrCreate(
            ['reference' => 'TXN20260525005'],
            [
                'session_id' => 'NIP-2818802',
                'user_id' => $james->id,
                'wallet_id' => $james->wallet?->id,
                'type' => TransactionType::WalletTransferOut,
                'direction' => TransactionDirection::Debit,
                'amount' => 67_000,
                'status' => TransactionStatus::Failed,
                'counterparty_name' => 'FCMB',
                'counterparty_account' => '0000000000',
                'counterparty_bank' => 'FCMB',
                'narrative' => 'NIP to FCMB — invalid beneficiary',
                'created_at' => now()->subHours(3),
            ],
        );

        for ($i = 0; $i < 8; $i++) {
            Transaction::query()->updateOrCreate(
                ['reference' => 'TXN20260525GT'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)],
                [
                    'session_id' => 'NIP-GTB-FAIL-'.($i + 1),
                    'user_id' => $david->id,
                    'wallet_id' => $david->wallet?->id,
                    'type' => TransactionType::WalletTransferOut,
                    'direction' => TransactionDirection::Debit,
                    'amount' => 10_000 + ($i * 1000),
                    'status' => $i < 2 ? TransactionStatus::Failed : TransactionStatus::Success,
                    'counterparty_name' => 'GTBank',
                    'counterparty_account' => '0123847291',
                    'counterparty_bank' => 'GTBank',
                    'narrative' => 'NIP outbound to GTBank',
                    'created_at' => now()->subHours(20)->addMinutes($i * 15),
                ],
            );
        }
    }
}
