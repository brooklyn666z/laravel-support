<?php

declare(strict_types=1);

namespace Fillindev\Support;

final class TicketNumberGenerator
{
    public function fromId(int $id, ?int $year = null): string
    {
        return sprintf('SUP-%d-%05d', $year ?? (int) now()->format('Y'), $id);
    }
}
