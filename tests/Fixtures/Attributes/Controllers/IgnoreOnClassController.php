<?php

namespace Tests\Fixtures\Attributes\Controllers;

use Poshtive\Router\Attributes\IgnoreParentMiddleware;
use Poshtive\Router\Attributes\Middleware;
use Tests\Fixtures\Attributes\Support\MidController;

#[IgnoreParentMiddleware]
#[Middleware('bindings')]
class IgnoreOnClassController extends MidController
{
    #[Middleware('audit')]
    public function update(): void {}
}
