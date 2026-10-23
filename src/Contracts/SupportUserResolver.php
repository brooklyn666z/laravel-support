<?php

declare(strict_types=1);

namespace Fillindev\Support\Contracts;

/**
 * Загрузка пользователя хоста по типу модели и id из таблиц пакета.
 */
interface SupportUserResolver
{
    public function resolve(string $type, int|string $id): ?SupportableUser;
}
