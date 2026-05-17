<?php

return [
    'use_demo_otp' => env('REGISTRATION_USE_DEMO_OTP', true),
    'demo_otp_code' => env('REGISTRATION_DEMO_OTP_CODE', '000000'),

    'otp_expiry_minutes' => (int) env('REGISTRATION_OTP_EXPIRY_MINUTES', 5),
    'complete_within_minutes' => (int) env('REGISTRATION_COMPLETE_WITHIN_MINUTES', 60),
    'max_verification_attempts' => (int) env('REGISTRATION_MAX_VERIFICATION_ATTEMPTS', 5),
    'resend_throttle_seconds' => (int) env('REGISTRATION_RESEND_THROTTLE_SECONDS', 60),
];
