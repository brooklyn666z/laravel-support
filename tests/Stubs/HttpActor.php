<?php

declare(strict_types=1);

namespace Fillindev\Support\Tests\Stubs;

use Fillindev\Support\Contracts\SupportableUser;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

final class HttpActor implements AuthenticatableContract, SupportableUser
{
    use Authenticatable;

    public string $password = '';

    public ?string $remember_token = null;

    public function __construct(
        private readonly int $id = 7,
        private readonly string $name = 'Клиент',
        private readonly ?string $email = 'client@example.com',
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): int
    {
        return $this->id;
    }

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

    public function getSupportPhoneCode(): ?string
    {
        return null;
    }

    public function getSupportPhone(): ?string
    {
        return null;
    }
}
