<?php

if (!function_exists('sanitize_name')) {
    function sanitize_name(string $name): string
    {
        // Remove special characters except spaces
        $cleaned = preg_replace('/[^A-Za-z\s]/', '', $name);
        
        // Trim whitespace and capitalize first letter
        return ucfirst(strtolower(trim($cleaned)));
    }
}

if (!function_exists('generate_otp')) {
    function generate_otp(int $length = 6): string
    {
        if (config('registration.use_demo_otp', true)) {
            $code = (string) config('registration.demo_otp_code', '000000');

            return str_pad(substr($code, 0, $length), $length, '0', STR_PAD_LEFT);
        }

        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('generate_wallet_account_number')) {
    function generate_wallet_account_number(): string
    {
        do {
            $number = str_pad((string) random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        } while (\App\Models\Wallet::query()->where('account_number', $number)->exists());

        return $number;
    }
}




?>