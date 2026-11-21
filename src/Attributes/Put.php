<?php

declare(strict_types=1);

namespace Poshtive\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Put extends HttpMethod
{
    public function verb(): string
    {
        return 'PUT';
    }
}
