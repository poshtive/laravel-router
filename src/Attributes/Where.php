<?php

declare(strict_types=1);

namespace Poshtive\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Where implements DiscoveryAttribute
{
    public const ALPHA = '[a-zA-Z]+';

    public const NUMERIC = '[0-9]+';

    public const ALPHANUMERIC = '[a-zA-Z0-9]+';

    public const UUID = '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}';

    public function __construct(
        public string $param,
        public string $constraint,
    ) {}
}
