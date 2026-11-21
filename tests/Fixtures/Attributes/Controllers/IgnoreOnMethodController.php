<?php

namespace Tests\Fixtures\Attributes\Controllers;

use Poshtive\Router\Attributes\IgnoreParentMiddleware;
use Poshtive\Router\Attributes\Middleware;
use Tests\Fixtures\Attributes\Support\MidController;

#[Middleware('bindings')]
class IgnoreOnMethodController extends MidController
{
    #[IgnoreParentMiddleware]
    #[Middleware('audit')]
    public function update(): void {}
}
