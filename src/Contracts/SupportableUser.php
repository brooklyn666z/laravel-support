<?php

declare(strict_types=1);

namespace Fillindev\Support\Contracts;

/**
 * Человек для пакета техподдержки.
 * Хост реализует контракт на своей модели пользователя.
 */
interface SupportableUser
{
    public function getSupportUserId(): int|string;

    public function getSupportDisplayName(): string;

    public function getSupportEmail(): ?string;
}
