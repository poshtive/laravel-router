<?php

namespace Tests\Fixtures\Invalid\Controllers;

use Poshtive\Router\Attributes\Get;

class BrokenController
{
    #[Get('items/{item}')]
    public function show(): void {}
}
