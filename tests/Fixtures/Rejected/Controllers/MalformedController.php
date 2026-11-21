<?php

namespace Tests\Fixtures\Rejected\Controllers;

use Poshtive\Router\Attributes\Get;
use Tests\Fixtures\Rejected\Support\Brew;

class MalformedController
{
    #[Brew]
    public function coffee(): void {}

    #[Get('items/{item}{detail}')]
    public function adjacent(string $item, string $detail): void {}

    #[Get('/orphans/{orphan}')]
    public function orphan(): void {}
}
