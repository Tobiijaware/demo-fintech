<?php

use App\Enums\OnboardingDocumentType;

return [
    'max_document_bytes' => (int) env('ONBOARDING_MAX_DOCUMENT_BYTES', 10 * 1024 * 1024),

    'document_types' => [
        OnboardingDocumentType::CacCertificate->value => 'CAC certificate',
        OnboardingDocumentType::UtilityBill->value => 'Utility bill (proof of address)',
        OnboardingDocumentType::BusinessRegistration->value => 'Business registration',
        OnboardingDocumentType::DirectorsId->value => 'Government-issued ID',
        OnboardingDocumentType::BankStatement->value => 'Bank statement',
        OnboardingDocumentType::Memorandum->value => 'Memorandum & articles',
        OnboardingDocumentType::Other->value => 'Other supporting document',
    ],

    /**
     * Dynamic requirements per applicant type and target tier.
     * Compliance can extend this config (or future DB-backed rules).
     *
     * identity: list of required checks — user must complete at least one of bvn|nin if multiple listed as OR,
     *           or all listed if you use required_identity_all (see below).
     * documents: required document_type keys
     */
    'tier_requirements' => [
        'customer' => [
            'tier_1' => [
                'label' => 'Tier 1 — Basic wallet',
                'description' => 'Verified identity wallet with standard daily limits.',
                'limits' => [
                    'daily_transfer' => 50_000,
                    'single_transfer' => 20_000,
                    'balance_limit' => 500_000,
                ],
                'identity_any_of' => ['bvn'],
                'documents' => [],
                'criteria' => [
                    [
                        'key' => 'firstname',
                        'type' => 'text',
                        'label' => 'First name',
                        'description' => 'Legal first name exactly as it appears on your BVN.',
                        'required' => true,
                        'group' => 'signup',
                        'order' => 1,
                    ],
                    [
                        'key' => 'lastname',
                        'type' => 'text',
                        'label' => 'Last name',
                        'description' => 'Legal surname exactly as it appears on your BVN.',
                        'required' => true,
                        'group' => 'signup',
                        'order' => 2,
                    ],
                    [
                        'key' => 'email',
                        'type' => 'email',
                        'label' => 'Email address',
                        'description' => 'Used for login, OTP verification, and account notifications.',
                        'required' => true,
                        'group' => 'signup',
                        'order' => 3,
                    ],
                    [
                        'key' => 'phone',
                        'type' => 'phone',
                        'label' => 'Phone number',
                        'description' => 'Active Nigerian mobile number for SMS alerts.',
                        'required' => true,
                        'group' => 'signup',
                        'order' => 4,
                    ],
                    [
                        'key' => 'date_of_birth',
                        'type' => 'date',
                        'label' => 'Date of birth',
                        'description' => 'Must match your BVN record.',
                        'required' => true,
                        'group' => 'signup',
                        'order' => 5,
                    ],
                    [
                        'key' => 'bvn',
                        'type' => 'identity_bvn',
                        'label' => 'BVN verification',
                        'description' => 'Enter your 11-digit Bank Verification Number. We will verify it matches the name you provided.',
                        'required' => true,
                        'group' => 'signup',
                        'order' => 6,
                    ],
                ],
            ],
            'tier_2' => [
                'label' => 'Tier 2 — Verified customer',
                'description' => 'Higher limits after identity and address verification.',
                'limits' => [
                    'daily_transfer' => 200_000,
                    'single_transfer' => 100_000,
                    'balance_limit' => 0,
                ],
                'identity_any_of' => ['bvn', 'nin'],
                'documents' => ['utility_bill', 'directors_id'],
                'criteria' => [
                    [
                        'key' => 'bvn',
                        'type' => 'identity_bvn',
                        'label' => 'BVN verification',
                        'description' => 'Verify with your 11-digit BVN.',
                        'required' => true,
                        'group' => 'identity',
                        'rule_group' => 'identity_all',
                        'order' => 1,
                    ],
                    [
                        'key' => 'nin',
                        'type' => 'identity_nin',
                        'label' => 'NIN verification',
                        'description' => 'Verify with your National ID (NIN).',
                        'required' => true,
                        'group' => 'identity',
                        'rule_group' => 'identity_all',
                        'order' => 2,
                    ],
                    [
                        'key' => 'utility_bill',
                        'type' => 'document',
                        'label' => 'Utility bill (proof of address)',
                        'description' => 'Upload a recent utility bill showing your residential address.',
                        'required' => true,
                        'group' => 'documents',
                        'order' => 3,
                        'config' => ['document_type' => 'utility_bill'],
                    ],
                    [
                        'key' => 'directors_id',
                        'type' => 'document',
                        'label' => 'Government-issued ID',
                        'description' => 'Upload a valid government photo ID.',
                        'required' => true,
                        'group' => 'documents',
                        'order' => 4,
                        'config' => ['document_type' => 'directors_id'],
                    ],
                ],
            ],
            'tier_3' => [
                'label' => 'Tier 3 — Premium',
                'description' => 'Full limits with enhanced due diligence.',
                'limits' => [
                    'daily_transfer' => 1_000_000,
                    'single_transfer' => 500_000,
                    'balance_limit' => 0,
                ],
                'identity_any_of' => ['bvn', 'nin'],
                'documents' => ['utility_bill', 'directors_id', 'bank_statement'],
            ],
        ],
        'agent' => [
            'tier_1' => [
                'label' => 'Tier 1 — Agent basic',
                'description' => 'Entry agent profile.',
                'identity_any_of' => ['bvn', 'nin'],
                'documents' => [],
            ],
            'tier_2' => [
                'label' => 'Tier 2 — Licensed agent',
                'description' => 'Standard PSSP agent onboarding.',
                'identity_any_of' => ['bvn', 'nin'],
                'documents' => ['cac_certificate', 'utility_bill', 'directors_id'],
            ],
            'tier_3' => [
                'label' => 'Tier 3 — Super agent',
                'description' => 'Super agent with regional float.',
                'identity_any_of' => ['bvn', 'nin'],
                'documents' => ['cac_certificate', 'utility_bill', 'directors_id', 'business_registration', 'bank_statement'],
            ],
        ],
    ],

    /** Legacy flat list — fallback if tier key missing */
    'required_documents' => [
        'agent' => ['cac_certificate', 'utility_bill', 'directors_id'],
        'customer' => ['directors_id'],
    ],

    'default_customer_target_tier' => 'tier_2',
];
