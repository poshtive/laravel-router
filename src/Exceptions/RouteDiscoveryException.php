<?php

declare(strict_types=1);

namespace Poshtive\Router\Exceptions;

use Poshtive\Router\Discovery\Diagnostic;
use RuntimeException;

class RouteDiscoveryException extends RuntimeException
{
    /** @param list<Diagnostic> $diagnostics */
    public function __construct(private readonly array $diagnostics)
    {
        parent::__construct(implode(PHP_EOL, array_map(
            fn (Diagnostic $diagnostic): string => $diagnostic->message,
            $diagnostics,
        )));
    }

    /** @return list<Diagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
