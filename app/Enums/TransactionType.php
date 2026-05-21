<?php

namespace App\Enums;

enum TransactionType: string
{
    case WalletTransferOut = 'wallet_transfer_out';
    case WalletTransferIn = 'wallet_transfer_in';
    case TransferFee = 'transfer_fee';
    case Airtime = 'airtime';
    case Bills = 'bills';
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';
}
