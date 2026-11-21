<?php

namespace Tests\Fixtures\Attributes\Controllers;

use Poshtive\Router\Attributes\Middleware;
use Tests\Fixtures\Attributes\Support\MidController;

#[Middleware('auth')]
#[Middleware('verified')]
class MiddlewareController extends MidController
{
    #[Middleware('can:update,post', 'auth')]
    #[Middleware('throttle')]
    public function update(): void {}
}
