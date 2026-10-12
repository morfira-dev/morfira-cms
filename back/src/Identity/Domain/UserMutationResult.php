<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

enum UserMutationResult
{
    case Applied;
    case NotFound;
    case LastOwner;
}
