<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests\Stubs;

use Fillindev\Support\Contracts\SupportableUser;

final class FakeUser implements SupportableUser
{
    public function __construct(
        private readonly int $id = 1,
        private readonly string $name = 'Иван',
        private readonly ?string $email = 'ivan@example.com',
    ) {}

    public function getSupportUserId(): int|string
    {
        return $this->id;
    }

    public function getSupportDisplayName(): string
    {
        return $this->name;
    }

    public function getSupportEmail(): ?string
    {
        return $this->email;
    }
}
