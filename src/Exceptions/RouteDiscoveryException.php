<?php

namespace Poshtive\Router\Exceptions;

use RuntimeException;

class RouteDiscoveryException extends RuntimeException
{
    public function __construct(array $messages)
    {
        parent::__construct(implode(PHP_EOL, $messages));
    }
}
