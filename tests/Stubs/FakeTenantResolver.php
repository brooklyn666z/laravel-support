<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests\Stubs;

use Fillindev\Support\Contracts\SupportTenant;
use Fillindev\Support\Contracts\SupportTenantResolver;

final class FakeTenantResolver implements SupportTenantResolver
{
    public function resolve(int|string|null $id): ?SupportTenant
    {
        if ($id === null) {
            return null;
        }

        return new FakeTenant((int) $id);
    }
}
