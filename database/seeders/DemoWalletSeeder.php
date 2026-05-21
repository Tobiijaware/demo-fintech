<?php

namespace Database\Seeders;

use App\Enums\TransactionDirection;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Enums\WalletStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\WalletService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoWalletSeeder extends Seeder
{
    /**
     * Demo mobile customers for wallet transfer & history.
     * PIN for all: 1234 (must be set via app or seeded hashed).
     */
    public function run(): void
    {
        $walletService = app(WalletService::class);

        $customers = [
            [
                'email' => 'david.adeyemi@demo.ng',
                'firstname' => 'David',
                'lastname' => 'Adeyemi',
                'phone' => '08031234567',
                'account_number' => '3712345001',
                'balance' => 125_000,
            ],
            [
                'email' => 'james.obi@demo.ng',
                'firstname' => 'James',
                'lastname' => 'Obi',
                'phone' => '08029876543',
                'account_number' => '3045678912',
                'balance' => 85_000,
            ],
            [
                'email' => 'tunde.adeyemi@demo.ng',
                'firstname' => 'Tunde',
                'lastname' => 'Adeyemi',
                'phone' => '08051112233',
                'account_number' => '0219616895',
                'balance' => 45_000,
            ],
            [
                'email' => 'annette.black@demo.ng',
                'firstname' => 'Annette',
                'lastname' => 'Black',
                'phone' => '08067778899',
                'account_number' => '2345327854',
                'balance' => 62_500,
            ],
        ];

        $users = [];

        foreach ($customers as $row) {
            $user = User::query()->updateOrCreate(
                ['email' => $row['email']],
                [
                    'firstname' => $row['firstname'],
                    'lastname' => $row['lastname'],
                    'phone' => $row['phone'],
                    'password' => Hash::make('Password123!'),
                    'pin' => Hash::make('1234'),
                    'user_type' => UserType::Customer,
                    'role' => UserRole::Customer,
                    'status' => UserStatus::Approved,
                    'email_verified_at' => now(),
                ],
            );

            Wallet::query()->updateOrCreate(
                ['user_id' => $user->id, 'currency' => 'NGN'],
                [
                    'account_number' => $row['account_number'],
                    'balance' => $row['balance'],
                    'status' => WalletStatus::Active,
                ],
            );

            $users[$row['account_number']] = $user;
        }

        $david = $users['3712345001']->load('wallet');
        $james = $users['3045678912']->load('wallet');
        $tunde = $users['0219616895']->load('wallet');

        $this->seedTransaction([
            'reference' => 'TXN20260518001',
            'session_id' => '2026051818153344556677',
            'user_id' => $david->id,
            'wallet_id' => $david->wallet->id,
            'type' => TransactionType::WalletTransferIn,
            'direction' => TransactionDirection::Credit,
            'amount' => 5000,
            'counterparty_name' => 'JAMES OBI',
            'counterparty_account' => '3045678912',
            'counterparty_bank' => 'Xpress Wallet',
            'narrative' => 'Transfer from JAMES OBI',
            'created_at' => now()->subDays(3)->setTime(18, 15),
            'meta' => [
                'from_name' => 'James Obi',
                'from_account' => '3045678912',
                'from_bank' => 'Xpress Wallet',
            ],
        ]);

        $this->seedTransaction([
            'reference' => 'TXN20260518002',
            'session_id' => '2026051117147752109876',
            'user_id' => $david->id,
            'wallet_id' => $david->wallet->id,
            'type' => TransactionType::WalletTransferOut,
            'direction' => TransactionDirection::Debit,
            'amount' => 5000,
            'fee' => 50,
            'counterparty_name' => 'TUNDE ADEYEMI',
            'counterparty_account' => '0219616895',
            'counterparty_bank' => 'Xpress Wallet',
            'narrative' => 'Transfer to TUNDE ADEYEMI',
            'created_at' => now()->subDays(3)->setTime(20, 40),
            'meta' => [
                'from_name' => 'David Adeyemi',
                'from_account' => '3712345001',
                'from_bank' => 'Xpress Wallet',
            ],
        ]);

        $this->seedTransaction([
            'reference' => 'TXN20260518003',
            'session_id' => '2026051814225566778899',
            'user_id' => $david->id,
            'wallet_id' => $david->wallet->id,
            'type' => TransactionType::Airtime,
            'direction' => TransactionDirection::Debit,
            'amount' => 1500,
            'status' => TransactionStatus::Failed,
            'counterparty_name' => 'MTN Nigeria',
            'counterparty_account' => '8031234567',
            'counterparty_bank' => 'MTN',
            'narrative' => 'Airtime purchase',
            'created_at' => now()->subDays(3)->setTime(14, 22),
        ]);

        $this->seedTransaction([
            'reference' => 'TXN20260520001',
            'session_id' => '2026052009301122334455',
            'user_id' => $james->id,
            'wallet_id' => $james->wallet->id,
            'type' => TransactionType::WalletTransferOut,
            'direction' => TransactionDirection::Debit,
            'amount' => 5000,
            'fee' => 50,
            'counterparty_name' => 'DAVID ADEYEMI',
            'counterparty_account' => '3712345001',
            'counterparty_bank' => 'Xpress Wallet',
            'narrative' => 'Transfer to DAVID ADEYEMI',
            'created_at' => now()->subDays(1)->setTime(9, 30),
        ]);

        $this->seedTransaction([
            'reference' => 'TXN20260520002',
            'session_id' => '2026052009456677889900',
            'user_id' => $tunde->id,
            'wallet_id' => $tunde->wallet->id,
            'type' => TransactionType::WalletTransferIn,
            'direction' => TransactionDirection::Credit,
            'amount' => 5000,
            'counterparty_name' => 'DAVID ADEYEMI',
            'counterparty_account' => '3712345001',
            'counterparty_bank' => 'Xpress Wallet',
            'narrative' => 'Transfer from DAVID ADEYEMI',
            'created_at' => now()->subDays(1)->setTime(20, 45),
        ]);

        foreach ($users as $user) {
            if (! $user->wallet) {
                $walletService->createNgnWallet($user);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function seedTransaction(array $data): void
    {
        Transaction::query()->updateOrCreate(
            ['reference' => $data['reference']],
            array_merge([
                'fee' => 0,
                'currency' => 'NGN',
                'status' => TransactionStatus::Success,
            ], $data),
        );
    }
}
