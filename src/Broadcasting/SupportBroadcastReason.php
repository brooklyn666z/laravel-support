<?php

declare(strict_types=1);

namespace Fillindev\Support\Broadcasting;

enum SupportBroadcastReason: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case Status = 'status';
    case Priority = 'priority';
    case Category = 'category';
    case Closed = 'closed';
    case Reopened = 'reopened';
}
