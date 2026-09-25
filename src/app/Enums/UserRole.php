<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'superadmin';
    case TenantAdmin = 'tenantadmin';
    case Customer = 'customer';
}
