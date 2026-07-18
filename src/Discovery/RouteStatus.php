<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

enum RouteStatus: string
{
    case Registered = 'registered';
    case Skipped = 'skipped';
    case Invalid = 'invalid';
    case Discarded = 'discarded';
}
