<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests\Stubs;

use Fillindev\Support\Contracts\SupportTenant;

final class FakeTenant implements SupportTenant
{
    public function __construct(
        private readonly int $id = 10,
        private readonly string $label = 'Ромашка',
    ) {}

    public function getSupportTenantId(): int|string
    {
        return $this->id;
    }

    public function getSupportTenantLabel(): string
    {
        return $this->label;
    }
}
