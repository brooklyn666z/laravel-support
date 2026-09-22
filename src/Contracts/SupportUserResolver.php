<?php

declare(strict_types=1);

namespace Fillindev\Support\Contracts;

/**
 * Загрузка пользователя хоста по logical id из таблиц пакета.
 */
interface SupportUserResolver
{
    public function resolve(int|string $id): ?SupportableUser;
}
