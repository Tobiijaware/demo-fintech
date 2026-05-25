<?php

namespace App\Enums;

enum SupportChannel: string
{
    case VoiceCall = 'Voice call';
    case Whatsapp = 'Whatsapp';
    case InAppChat = 'In-app chat';
    case Email = 'Email';
    case Phone = 'Phone';
    case InApp = 'In-app';
    case Agent = 'Agent';
}
