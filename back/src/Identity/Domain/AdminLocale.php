<?php

declare(strict_types=1);

namespace Cms\Identity\Domain;

enum AdminLocale: string
{
    case English = 'en';
    case Russian = 'ru';
}
