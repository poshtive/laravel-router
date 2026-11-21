<?php

namespace Tests\Fixtures\Rejected\Support;

use Attribute;
use Poshtive\Router\Attributes\HttpMethod;

#[Attribute(Attribute::TARGET_METHOD)]
final class Brew extends HttpMethod
{
    public function verb(): string
    {
        return 'brew';
    }
}
