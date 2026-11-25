<?php

declare(strict_types=1);

namespace Cms\Content\Domain;

enum PageReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';
}
