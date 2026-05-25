<?php

namespace App\Enums;

enum SystemSettingGroup: string
{
    case General = 'general';
    case Security = 'security';
    case Notifications = 'notifications';
    case Features = 'features';
}
