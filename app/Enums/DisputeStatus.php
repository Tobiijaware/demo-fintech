<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'Open';
    case UnderReview = 'Under review';
    case Won = 'Won';
    case Lost = 'Lost';
    case Closed = 'Closed';
}
