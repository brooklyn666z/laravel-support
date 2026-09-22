<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests\Stubs;

use Fillindev\Support\Contracts\SupportableUser;
use Fillindev\Support\Contracts\SupportUserResolver;

final class FakeUserResolver implements SupportUserResolver
{
    public function resolve(int|string $id): ?SupportableUser
    {
        return new FakeUser((int) $id);
    }
}
