<?php

namespace App\Services\Wallet;

use App\Enums\TransactionDirection;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WalletStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Auth\PinService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransactionService
{
    public const TRANSFER_FEE = 50.0;

    public const MIN_TRANSFER = 100.0;

    public function __construct(
        private WalletService $walletService,
        private PinService $pinService,
    ) {}

    /**
     * @return array{account_number: string, account_name: string, bank_name: string}
     */
    public function resolveWalletAccount(User $sender, string $rawAccount): array
    {
        $accountNumber = preg_replace('/\D/', '', $rawAccount) ?? '';

        if (strlen($accountNumber) !== 10) {
            throw new InvalidArgumentException('Enter a valid 10-digit wallet account number.');
        }

        $senderWallet = $this->walletService->getNgnBalance($sender);

        if ($senderWallet->account_number === $accountNumber) {
            throw new InvalidArgumentException('You cannot transfer to your own wallet.');
        }

        $wallet = Wallet::query()
            ->with('user')
            ->where('account_number', $accountNumber)
            ->where('status', WalletStatus::Active)
            ->first();

        if (! $wallet?->user) {
            throw new InvalidArgumentException('Account not found. Check the wallet number and try again.');
        }

        $name = trim("{$wallet->user->firstname} {$wallet->user->lastname}");

        return [
            'account_number' => $wallet->account_number,
            'account_name' => strtoupper($name),
            'bank_name' => config('app.wallet_bank_name', 'Xpress MFB'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transfer(
        User $sender,
        string $toAccount,
        float $amount,
        string $pin,
        ?string $remark = null,
    ): array {
        if ($amount < self::MIN_TRANSFER) {
            throw new InvalidArgumentException('Minimum transfer amount is ₦'.number_format(self::MIN_TRANSFER, 0));
        }

        $resolved = $this->resolveWalletAccount($sender, $toAccount);
        $this->pinService->verify($sender, $pin);

        return DB::transaction(function () use ($sender, $resolved, $amount, $remark) {
            $senderWallet = Wallet::query()
                ->where('user_id', $sender->id)
                ->where('currency', 'NGN')
                ->lockForUpdate()
                ->firstOrFail();

            $recipientWallet = Wallet::query()
                ->where('account_number', $resolved['account_number'])
                ->lockForUpdate()
                ->firstOrFail();

            $totalDebit = $amount + self::TRANSFER_FEE;

            if ((float) $senderWallet->balance < $totalDebit) {
                throw new InvalidArgumentException('Insufficient wallet balance for this transfer.');
            }

            $sessionId = now()->format('YmdHis').Str::upper(Str::random(8));
            $debitRef = 'TXN'.now()->format('ymdHis').Str::upper(Str::random(6));
            $creditRef = 'TXN'.now()->format('ymdHis').Str::upper(Str::random(6));
            $feeRef = 'TXN'.now()->format('ymdHis').Str::upper(Str::random(6));

            $recipient = $recipientWallet->user;
            $senderName = trim("{$sender->firstname} {$sender->lastname}");
            $recipientName = $resolved['account_name'];
            $bank = $resolved['bank_name'];

            $senderWallet->decrement('balance', $totalDebit);
            $recipientWallet->increment('balance', $amount);

            $debit = Transaction::query()->create([
                'reference' => $debitRef,
                'session_id' => $sessionId,
                'user_id' => $sender->id,
                'wallet_id' => $senderWallet->id,
                'type' => TransactionType::WalletTransferOut,
                'direction' => TransactionDirection::Debit,
                'amount' => $amount,
                'fee' => self::TRANSFER_FEE,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => $recipientName,
                'counterparty_account' => $recipientWallet->account_number,
                'counterparty_bank' => $bank,
                'narrative' => $remark ?: "Transfer to {$recipientName}",
                'meta' => [
                    'from_name' => $senderName,
                    'from_account' => $senderWallet->account_number,
                    'from_bank' => $bank,
                ],
            ]);

            $credit = Transaction::query()->create([
                'reference' => $creditRef,
                'session_id' => $sessionId,
                'user_id' => $recipient->id,
                'wallet_id' => $recipientWallet->id,
                'type' => TransactionType::WalletTransferIn,
                'direction' => TransactionDirection::Credit,
                'amount' => $amount,
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => strtoupper($senderName),
                'counterparty_account' => $senderWallet->account_number,
                'counterparty_bank' => $bank,
                'narrative' => "Transfer from ".strtoupper($senderName),
                'linked_transaction_id' => $debit->id,
                'meta' => [
                    'from_name' => strtoupper($senderName),
                    'from_account' => $senderWallet->account_number,
                    'from_bank' => $bank,
                ],
            ]);

            $debit->update(['linked_transaction_id' => $credit->id]);

            Transaction::query()->create([
                'reference' => $feeRef,
                'session_id' => $sessionId,
                'user_id' => $sender->id,
                'wallet_id' => $senderWallet->id,
                'type' => TransactionType::TransferFee,
                'direction' => TransactionDirection::Debit,
                'amount' => self::TRANSFER_FEE,
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
                'counterparty_name' => 'Transfer fee',
                'counterparty_bank' => $bank,
                'narrative' => 'Wallet transfer fee',
                'linked_transaction_id' => $debit->id,
            ]);

            return [
                'transaction_reference' => $debitRef,
                'session_id' => $sessionId,
                'amount' => $amount,
                'fee' => self::TRANSFER_FEE,
                'recipient' => $resolved,
                'balance' => (float) $senderWallet->fresh()->balance,
            ];
        });
    }

    public function listForUser(User $user, ?string $search = null, int $perPage = 30): LengthAwarePaginator
    {
        return $this->userTransactionsQuery($user->id, $search)
            ->paginate($perPage);
    }

    public function findForUser(User $user, string $reference): Transaction
    {
        return $this->userTransactionsQuery($user->id)
            ->where('reference', $reference)
            ->firstOrFail();
    }

    public function adminList(?string $search = null, ?string $status = null, int $perPage = 40): LengthAwarePaginator
    {
        $query = Transaction::query()
            ->with(['user:id,firstname,lastname,email,phone', 'wallet:id,account_number'])
            ->orderByDesc('created_at');

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('reference', 'like', $term)
                    ->orWhere('session_id', 'like', $term)
                    ->orWhere('counterparty_name', 'like', $term)
                    ->orWhere('counterparty_account', 'like', $term)
                    ->orWhere('narrative', 'like', $term);
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return array<string, int|float>
     */
    public function adminStats(): array
    {
        $today = now()->startOfDay();

        $todayQuery = Transaction::query()->where('created_at', '>=', $today);

        return [
            'total_today' => (int) $todayQuery->count(),
            'volume_today' => (float) $todayQuery->sum('amount'),
            'successful_today' => (int) (clone $todayQuery)->where('status', TransactionStatus::Success)->count(),
            'failed_today' => (int) (clone $todayQuery)->where('status', TransactionStatus::Failed)->count(),
            'pending_today' => (int) (clone $todayQuery)->where('status', TransactionStatus::Pending)->count(),
            'wallet_transfers_today' => (int) (clone $todayQuery)
                ->whereIn('type', [
                    TransactionType::WalletTransferOut,
                    TransactionType::WalletTransferIn,
                ])
                ->count(),
        ];
    }

    private function userTransactionsQuery(int $userId, ?string $search = null): Builder
    {
        $query = Transaction::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($search) {
            $term = '%'.$search.'%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('reference', 'like', $term)
                    ->orWhere('counterparty_name', 'like', $term)
                    ->orWhere('narrative', 'like', $term);
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function formatForMobile(Transaction $tx, User $user): array
    {
        $wallet = $user->wallet;
        $meta = $tx->meta ?? [];
        $isCredit = $tx->direction === TransactionDirection::Credit;

        $fromName = $isCredit
            ? ($tx->counterparty_name ?? '')
            : ($meta['from_name'] ?? trim("{$user->firstname} {$user->lastname}"));
        $fromAccount = $isCredit
            ? ($tx->counterparty_account ?? '')
            : ($meta['from_account'] ?? $wallet?->account_number ?? '');
        $toName = $isCredit
            ? (trim("{$user->firstname} {$user->lastname}"))
            : ($tx->counterparty_name ?? '');
        $toAccount = $isCredit
            ? ($wallet?->account_number ?? '')
            : ($tx->counterparty_account ?? '');

        return [
            'id' => $tx->reference,
            'reference' => $tx->reference,
            'session_id' => $tx->session_id,
            'title' => $this->titleFor($tx),
            'amount' => (float) $tx->amount,
            'fee' => (float) $tx->fee,
            'is_credit' => $isCredit,
            'status' => $tx->status->value,
            'type' => $tx->type->value,
            'occurred_at' => $tx->created_at?->toIso8601String(),
            'from_name' => $fromName,
            'from_account' => $fromAccount,
            'from_bank' => $meta['from_bank'] ?? config('app.wallet_bank_name', 'Xpress MFB'),
            'to_name' => $toName,
            'to_account' => $toAccount,
            'to_bank' => $tx->counterparty_bank ?? config('app.wallet_bank_name', 'Xpress MFB'),
            'remark' => $tx->narrative,
        ];
    }

    private function titleFor(Transaction $tx): string
    {
        return match ($tx->type) {
            TransactionType::WalletTransferOut => 'Transfer to '.($tx->counterparty_name ?? 'recipient'),
            TransactionType::WalletTransferIn => 'Transfer from '.($tx->counterparty_name ?? 'sender'),
            TransactionType::TransferFee => 'Transfer fee',
            TransactionType::Airtime => 'Airtime purchase',
            default => $tx->narrative ?? 'Transaction',
        };
    }
}
