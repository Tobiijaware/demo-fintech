<?php

namespace App\Enums;

enum KycLevel: string
{
    case IdentityVerification = 'identity_verification';
    case ProofOfAddress = 'proof_of_address';
}
