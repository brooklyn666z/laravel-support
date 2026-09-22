<?php

declare(strict_types=1);

namespace Fillindev\Support;

use InvalidArgumentException;

final class SupportIds
{
    public static function toInt(int|string $id): int
    {
        if (is_int($id)) {
            return $id;
        }

        if (preg_match('/^\d+$/', $id) === 1) {
            return (int) $id;
        }

        throw new InvalidArgumentException('Идентификатор поддержки должен быть целым числом.');
    }
}
