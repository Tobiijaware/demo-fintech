<?php

namespace Database\Seeders;

use App\Enums\DisputeStatus;
use App\Enums\ReversalStatus;
use App\Enums\SupportChannel;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Dispute;
use App\Models\ReversalRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupportSeeder extends Seeder
{
    public function run(): void
    {
        $support = User::query()->where('email', 'support@iwallet.demo')->first();
        $operations = User::query()->where('email', 'operations@iwallet.demo')->first();
        $settlement = User::query()->where('email', 'settlement@iwallet.demo')->first();

        $david = User::query()->where('email', 'david.adeyemi@demo.ng')->with('wallet')->first();
        $james = User::query()->where('email', 'james.obi@demo.ng')->with('wallet')->first();
        $tunde = User::query()->where('email', 'tunde.adeyemi@demo.ng')->with('wallet')->first();
        $annette = User::query()->where('email', 'annette.black@demo.ng')->with('wallet')->first();

        $txnDebit = Transaction::query()->where('reference', 'TXN20260518002')->first();
        $txnFailedAirtime = Transaction::query()->where('reference', 'TXN20260518003')->first();
        $txnJamesOut = Transaction::query()->where('reference', 'TXN20260520001')->first();
        $txnTundeIn = Transaction::query()->where('reference', 'TXN20260520002')->first();

        $tickets = [
            [
                'reference' => 'T-9281',
                'subject' => 'Adamu Yusuf — ₦15,000 debit, no credit',
                'description' => 'NIP to UBA · 09:14 today',
                'category' => TicketCategory::FailedTxn,
                'status' => TicketStatus::InReview,
                'priority' => TicketPriority::High,
                'channel' => SupportChannel::VoiceCall,
                'assignee_id' => $support?->id,
                'customer_user_id' => $david?->id,
                'customer_name' => 'David Adeyemi',
                'customer_phone' => '+234 803 123 4567',
                'customer_email' => $david?->email,
                'wallet_id' => $david?->wallet?->id,
                'sla_due_at' => now()->addHours(3)->addMinutes(12),
                'sla_breached' => false,
                'maker_id' => $support?->id,
                'metadata' => [
                    'txn_ref' => $txnDebit?->reference ?? 'TXN20260518002',
                    'wallet_display' => 'W-'.($david?->wallet?->id ?? '39102'),
                    'drawer_title' => 'Failed NIP transfer to UBA',
                    'disputed_txn_detail' => ($txnDebit?->reference ?? 'TXN20260518002').' — NIP transfer of ₦ 5,000. Debited from customer wallet. Beneficiary bank has not confirmed credit.',
                    'suggested_action' => 'Auto-suggested action: Initiate reversal if no credit confirmation by reconciliation cycle.',
                    'workflow_steps' => [
                        ['date' => now()->format('d M Y'), 'label' => 'Draft auto-generated from AMI logs', 'active' => true],
                        ['date' => now()->format('d M Y'), 'label' => 'Assigned to Bola Adesina'],
                    ],
                ],
                'created_at' => now()->setTime(9, 20),
            ],
            [
                'reference' => 'T-9280',
                'subject' => 'Blessing Okafor — Wallet locked',
                'description' => '3 failed PIN attempts · 08:55 today',
                'category' => TicketCategory::Lockout,
                'status' => TicketStatus::InReview,
                'priority' => TicketPriority::Normal,
                'channel' => SupportChannel::Whatsapp,
                'assignee_id' => $support?->id,
                'customer_name' => 'Annette Black',
                'customer_user_id' => $annette?->id,
                'customer_email' => $annette?->email,
                'wallet_id' => $annette?->wallet?->id,
                'sla_due_at' => now()->addMinutes(45),
                'sla_breached' => false,
                'maker_id' => $support?->id,
                'created_at' => now()->setTime(8, 55),
            ],
            [
                'reference' => 'T-9278',
                'subject' => 'Ngozi Okwuosa — Reversal not received',
                'description' => '₦48,000 · approved yesterday',
                'category' => TicketCategory::Reversal,
                'status' => TicketStatus::InReview,
                'priority' => TicketPriority::High,
                'channel' => SupportChannel::Email,
                'assignee_id' => $support?->id,
                'customer_name' => 'James Obi',
                'customer_user_id' => $james?->id,
                'customer_email' => $james?->email,
                'wallet_id' => $james?->wallet?->id,
                'sla_due_at' => now()->addHours(1)->addMinutes(8),
                'sla_breached' => false,
                'maker_id' => $support?->id,
                'metadata' => ['txn_ref' => $txnJamesOut?->reference ?? 'TXN20260520001'],
                'created_at' => now()->subDay()->setTime(16, 40),
            ],
            [
                'reference' => 'T-9274',
                'subject' => 'Chika Eze — Duplicate airtime purchase',
                'description' => '₦2,000 charged twice · 07:30 today',
                'category' => TicketCategory::FailedTxn,
                'status' => TicketStatus::Open,
                'priority' => TicketPriority::Normal,
                'channel' => SupportChannel::InAppChat,
                'assignee_id' => $support?->id,
                'customer_name' => 'David Adeyemi',
                'customer_user_id' => $david?->id,
                'customer_email' => $david?->email,
                'wallet_id' => $david?->wallet?->id,
                'sla_due_at' => now()->subHours(2),
                'sla_breached' => true,
                'maker_id' => $support?->id,
                'metadata' => ['txn_ref' => $txnFailedAirtime?->reference ?? 'TXN20260518003'],
                'created_at' => now()->setTime(7, 30),
            ],
            [
                'reference' => 'T-9272',
                'subject' => 'Tunde Balogun — PIN reset request',
                'description' => 'Forgot PIN · awaiting BVN match',
                'category' => TicketCategory::PinReset,
                'status' => TicketStatus::AwaitingCustomer,
                'priority' => TicketPriority::Normal,
                'channel' => SupportChannel::VoiceCall,
                'assignee_id' => $support?->id,
                'customer_name' => 'Tunde Adeyemi',
                'customer_user_id' => $tunde?->id,
                'customer_email' => $tunde?->email,
                'wallet_id' => $tunde?->wallet?->id,
                'sla_due_at' => now()->addHours(5)->addMinutes(40),
                'sla_breached' => false,
                'maker_id' => $support?->id,
                'created_at' => now()->setTime(6, 10),
            ],
            [
                'reference' => 'T-9265',
                'subject' => 'POS dispute — customer charged twice',
                'description' => '₦12,500 · TRM-88201',
                'category' => TicketCategory::Dispute,
                'status' => TicketStatus::Escalated,
                'priority' => TicketPriority::High,
                'channel' => SupportChannel::InAppChat,
                'assignee_id' => $support?->id,
                'customer_name' => 'Customer · W-99281',
                'customer_user_id' => $annette?->id,
                'wallet_id' => $annette?->wallet?->id,
                'sla_due_at' => now()->subHour(),
                'sla_breached' => true,
                'maker_id' => $support?->id,
                'metadata' => [
                    'txn_ref' => $txnDebit?->reference ?? 'TXN20260518002',
                    'wallet_display' => 'W-99281',
                ],
                'created_at' => now()->setTime(6, 10),
            ],
        ];

        $seededTickets = [];

        foreach ($tickets as $row) {
            $createdAt = $row['created_at'] ?? now();
            unset($row['created_at']);

            $ticket = SupportTicket::query()->updateOrCreate(
                ['reference' => $row['reference']],
                $row,
            );

            $ticket->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

            SupportTicketEvent::query()->updateOrCreate(
                [
                    'ticket_id' => $ticket->id,
                    'action' => 'created',
                ],
                [
                    'actor_id' => $support?->id,
                    'notes' => 'Ticket seeded for demo',
                    'created_at' => $createdAt,
                ],
            );

            $seededTickets[$row['reference']] = $ticket;
        }

        $reversals = [
            [
                'reference' => 'REV-2026-441',
                'ticket_id' => $seededTickets['T-9278']->id ?? null,
                'transaction_id' => $txnJamesOut?->id,
                'transaction_reference' => $txnJamesOut?->reference,
                'amount' => 5000,
                'reason' => 'Customer debited, beneficiary did not receive within 24h',
                'status' => ReversalStatus::PendingApproval,
                'maker_id' => $support?->id,
            ],
            [
                'reference' => 'REV-2026-438',
                'ticket_id' => $seededTickets['T-9281']->id ?? null,
                'transaction_id' => $txnDebit?->id,
                'transaction_reference' => $txnDebit?->reference,
                'amount' => 5000,
                'reason' => 'Failed NIP — auto-reversal not triggered',
                'status' => ReversalStatus::PendingApproval,
                'maker_id' => $operations?->id ?? $support?->id,
            ],
            [
                'reference' => 'REV-2026-430',
                'transaction_id' => $txnTundeIn?->id,
                'transaction_reference' => $txnTundeIn?->reference,
                'amount' => 5000,
                'reason' => 'Duplicate transfer — customer submitted bank statement',
                'status' => ReversalStatus::Approved,
                'maker_id' => $support?->id,
                'checker_id' => $settlement?->id,
                'reviewed_at' => now()->subDays(2),
                'checker_notes' => 'Approved after NIBSS trace confirmation',
            ],
            [
                'reference' => 'REV-2026-421',
                'amount' => 500000,
                'reason' => 'Wrong wallet credited — exceeds single reversal limit',
                'status' => ReversalStatus::Rejected,
                'maker_id' => $operations?->id,
                'checker_id' => $settlement?->id,
                'reviewed_at' => now()->subDays(5),
                'checker_notes' => 'Requires treasury manual adjustment',
            ],
        ];

        foreach ($reversals as $row) {
            ReversalRequest::query()->updateOrCreate(
                ['reference' => $row['reference']],
                $row,
            );
        }

        $disputes = [
            [
                'reference' => 'DSP-2026-088',
                'ticket_id' => $seededTickets['T-9265']->id ?? null,
                'transaction_reference' => $txnDebit?->reference,
                'amount' => 12500,
                'reason' => 'Customer claims double charge on POS',
                'status' => DisputeStatus::Open,
                'customer_name' => 'Customer · W-99281',
                'opened_at' => now()->subHours(6),
                'due_at' => now()->setTime(18, 0),
                'assignee_id' => null,
            ],
            [
                'reference' => 'DSP-2026-085',
                'transaction_reference' => $txnFailedAirtime?->reference,
                'amount' => 1500,
                'reason' => 'Goods not received — card not present',
                'status' => DisputeStatus::UnderReview,
                'customer_name' => 'David Adeyemi',
                'opened_at' => now()->subDays(3),
                'due_at' => now()->addDays(2),
                'assignee_id' => $operations?->id,
            ],
            [
                'reference' => 'DSP-2026-080',
                'transaction_reference' => $txnFailedAirtime?->reference,
                'amount' => 1500,
                'reason' => 'Wrong denomination delivered',
                'status' => DisputeStatus::Won,
                'customer_name' => 'Tunde Adeyemi',
                'opened_at' => now()->subDays(10),
                'assignee_id' => $support?->id,
                'resolution_notes' => 'Merchant refunded customer',
            ],
            [
                'reference' => 'DSP-2026-072',
                'transaction_reference' => $txnJamesOut?->reference,
                'amount' => 5000,
                'reason' => 'Unauthorized agent withdrawal',
                'status' => DisputeStatus::Lost,
                'customer_name' => 'James Obi',
                'opened_at' => now()->subDays(14),
                'assignee_id' => $support?->id,
                'resolution_notes' => 'Customer authorized transaction via OTP',
            ],
        ];

        foreach ($disputes as $row) {
            $dispute = Dispute::query()->updateOrCreate(
                ['reference' => $row['reference']],
                $row,
            );

            if ($row['reference'] === 'DSP-2026-088' && isset($seededTickets['T-9265'])) {
                $ticket = $seededTickets['T-9265'];
                $metadata = $ticket->metadata ?? [];
                $metadata['merchant'] = 'Mama Ngozi Provisions';
                $metadata['evidence_due'] = true;
                $ticket->update(['metadata' => $metadata]);
            }
        }
    }
}
