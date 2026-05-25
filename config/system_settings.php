<?php

return [

    /**
     * Default system settings keyed by field id (matches frontend settingsData.ts).
     * Each entry: group => SystemSettingGroup value, value => stored JSON scalar.
     */
    'defaults' => [
        'platform_name' => ['group' => 'general', 'value' => 'iWallet Back Office'],
        'default_locale' => ['group' => 'general', 'value' => 'en-NG'],
        'timezone' => ['group' => 'general', 'value' => 'Africa/Lagos'],
        'date_format' => ['group' => 'general', 'value' => 'dmy'],
        'compact_tables' => ['group' => 'general', 'value' => false],

        'mfa_required' => ['group' => 'security', 'value' => true],
        'session_timeout' => ['group' => 'security', 'value' => '30'],
        'ip_allowlist' => ['group' => 'security', 'value' => false],
        'dual_approval' => ['group' => 'security', 'value' => true],
        'audit_export_sign' => ['group' => 'security', 'value' => true],

        'notify_failed_login' => ['group' => 'notifications', 'value' => true],
        'notify_settlement' => ['group' => 'notifications', 'value' => true],
        'notify_kyc_sla' => ['group' => 'notifications', 'value' => false],
        'notify_aml_high' => ['group' => 'notifications', 'value' => true],
        'ops_email' => ['group' => 'notifications', 'value' => 'operations@iwallet.demo'],

        'ff_wallet_transfer' => ['group' => 'features', 'value' => true],
        'ff_agent_float' => ['group' => 'features', 'value' => true],
        'ff_str_automation' => ['group' => 'features', 'value' => false],
        'ff_new_kyc_modal' => ['group' => 'features', 'value' => true],
        'ff_chart_dashboard' => ['group' => 'features', 'value' => true],
    ],

    'integration_seeds' => [
        'swwipe' => [
            'name' => 'Swwipe',
            'description' => 'BVN/NIN identity verification',
            'status' => 'healthy',
            'latency' => '890 ms',
            'last_sync' => '5 min ago',
        ],
        'dojah' => [
            'name' => 'Dojah',
            'description' => 'Bank list and account resolution',
            'status' => 'healthy',
            'latency' => '420 ms',
            'last_sync' => '3 min ago',
        ],
        'email' => [
            'name' => 'Email (SendGrid)',
            'description' => 'Staff invites and compliance notices',
            'status' => 'healthy',
            'latency' => '310 ms',
            'last_sync' => '1 min ago',
        ],
    ],

];
