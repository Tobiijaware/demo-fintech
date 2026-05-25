<?php

namespace App\Services\Governance;

use App\Enums\UserType;
use App\Models\Agent;
use App\Models\SupportTicket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Backoffice\PermissionResolver;

class SearchService
{
    private const LIMIT = 5;

    public function __construct(private PermissionResolver $permissions) {}

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function search(User $actor, string $query): array
    {
        $term = trim($query);
        if ($term === '') {
            return $this->emptyResults();
        }

        $like = '%'.$term.'%';
        $results = $this->emptyResults();

        if ($this->permissions->can($actor, 'user_management', \App\Enums\PermissionLevel::Read)) {
            $results['staff'] = $this->searchStaff($like);
            $results['customers'] = $this->searchCustomers($like);
            $results['agents'] = $this->searchAgents($like);
        }

        if ($this->permissions->can($actor, 'transactions', \App\Enums\PermissionLevel::Read)) {
            $results['transactions'] = $this->searchTransactions($like);
            $results['support_tickets'] = $this->searchSupportTickets($like);
        }

        return $results;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function emptyResults(): array
    {
        return [
            'staff' => [],
            'customers' => [],
            'transactions' => [],
            'support_tickets' => [],
            'agents' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchStaff(string $like): array
    {
        return User::query()
            ->where('user_type', UserType::Staff)
            ->with('backofficeRole')
            ->where(function ($q) use ($like) {
                $q->where('email', 'like', $like)
                    ->orWhere('firstname', 'like', $like)
                    ->orWhere('lastname', 'like', $like)
                    ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", [$like]);
            })
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'type' => 'staff',
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->backofficeRole?->name,
                'hub' => $user->hub,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchCustomers(string $like): array
    {
        return User::query()
            ->where('user_type', UserType::Customer)
            ->where(function ($q) use ($like) {
                $q->where('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('firstname', 'like', $like)
                    ->orWhere('lastname', 'like', $like)
                    ->orWhereRaw("CONCAT(firstname, ' ', lastname) LIKE ?", [$like]);
            })
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'type' => 'customer',
                'name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchTransactions(string $like): array
    {
        return Transaction::query()
            ->where(function ($q) use ($like) {
                $q->where('reference', 'like', $like)
                    ->orWhere('narrative', 'like', $like)
                    ->orWhere('counterparty_name', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Transaction $tx) => [
                'reference' => $tx->reference,
                'type' => 'transaction',
                'amount' => $tx->amount,
                'status' => $tx->status?->value ?? $tx->status,
                'created_at' => $tx->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchSupportTickets(string $like): array
    {
        return SupportTicket::query()
            ->where(function ($q) use ($like) {
                $q->where('reference', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('customer_email', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (SupportTicket $ticket) => [
                'reference' => $ticket->reference,
                'type' => 'support_ticket',
                'subject' => $ticket->subject,
                'status' => $ticket->status?->value ?? $ticket->status,
                'priority' => $ticket->priority?->value ?? $ticket->priority,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchAgents(string $like): array
    {
        return Agent::query()
            ->where(function ($q) use ($like) {
                $q->where('code', 'like', $like)
                    ->orWhere('business_name', 'like', $like)
                    ->orWhere('proprietor_name', 'like', $like)
                    ->orWhere('location', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Agent $agent) => [
                'code' => $agent->code,
                'type' => 'agent',
                'business_name' => $agent->business_name,
                'proprietor_name' => $agent->proprietor_name,
                'region' => $agent->region,
                'status' => $agent->status?->value ?? $agent->status,
            ])
            ->all();
    }
}
