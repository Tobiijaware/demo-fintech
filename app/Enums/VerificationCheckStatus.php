<?php

namespace App\Enums;

enum VerificationCheckStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case BvnMismatch = 'bvn_mismatch';
    case NinMismatch = 'nin_mismatch';
    case DocumentPending = 'document_pending';
}
