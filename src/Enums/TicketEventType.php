<?php

declare(strict_types=1);

namespace Fillindev\Support\Enums;

enum TicketEventType: string
{
    case Created = 'created';
    case Assigned = 'assigned';
    case StatusChanged = 'status_changed';
    case PriorityChanged = 'priority_changed';
    case CategoryChanged = 'category_changed';
    case Reopened = 'reopened';
    case Closed = 'closed';
    case MessagePosted = 'message_posted';
}
