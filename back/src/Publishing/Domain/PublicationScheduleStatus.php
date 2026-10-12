<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

enum PublicationScheduleStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
