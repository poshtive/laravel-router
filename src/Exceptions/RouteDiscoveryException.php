<?php

declare(strict_types=1);

namespace Poshtive\Router\Exceptions;

use RuntimeException;

class RouteDiscoveryException extends RuntimeException
{
    /** @param list<string> $messages */
    public function __construct(array $messages)
    {
        parent::__construct(implode(PHP_EOL, $messages));
    }
}
