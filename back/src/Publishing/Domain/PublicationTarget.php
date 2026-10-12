<?php

declare(strict_types=1);

namespace Cms\Publishing\Domain;

enum PublicationTarget: string
{
    case Page = 'page';
    case Entry = 'entry';
}
