<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests\Stubs;

use Fillindev\Support\Contracts\SupportTenant;
use Fillindev\Support\Contracts\SupportTenantResolver;

final class FakeTenantResolver implements SupportTenantResolver
{
    public static ?SupportTenant $current = null;

    public function resolve(int|string|null $id): ?SupportTenant
    {
        if ($id === null) {
            return null;
        }

        return new FakeTenant((int) $id);
    }

    public function current(): ?SupportTenant
    {
        return self::$current;
    }
}
