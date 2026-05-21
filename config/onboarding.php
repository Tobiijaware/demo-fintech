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
                'description' => 'Email verified wallet with daily limits.',
                'identity_any_of' => ['bvn', 'nin'],
                'documents' => [],
            ],
            'tier_2' => [
                'label' => 'Tier 2 — Verified customer',
                'description' => 'Higher limits after identity and address verification.',
                'identity_any_of' => ['bvn', 'nin'],
                'documents' => ['utility_bill', 'directors_id'],
            ],
            'tier_3' => [
                'label' => 'Tier 3 — Premium',
                'description' => 'Full limits with enhanced due diligence.',
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
