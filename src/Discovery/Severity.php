<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

enum Severity: string
{
    /** A deliberate decision, such as a route excluded by #[DoNotDiscover]. */
    case Info = 'info';

    /** Configuration that is probably wrong but does not break routing. */
    case Warning = 'warning';

    /** A route that cannot be registered. Strict mode throws. */
    case Error = 'error';
}
