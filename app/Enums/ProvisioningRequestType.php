<?php

namespace App\Enums;

enum ProvisioningRequestType: string
{
    case NewUser = 'new_user';
    case RoleChange = 'role_change';
}
