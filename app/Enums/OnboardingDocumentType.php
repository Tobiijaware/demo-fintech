<?php

namespace App\Enums;

enum OnboardingDocumentType: string
{
    case CacCertificate = 'cac_certificate';
    case UtilityBill = 'utility_bill';
    case BusinessRegistration = 'business_registration';
    case DirectorsId = 'directors_id';
    case BankStatement = 'bank_statement';
    case Memorandum = 'memorandum';
    case Other = 'other';

    public function label(): string
    {
        return config("onboarding.document_types.{$this->value}", $this->value);
    }
}
