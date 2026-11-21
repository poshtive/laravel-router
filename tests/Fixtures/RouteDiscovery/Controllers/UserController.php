<?php

namespace Tests\Fixtures\RouteDiscovery\Controllers;

use Poshtive\Router\Attributes\Middleware;
use Poshtive\Router\Attributes\Put;
use Poshtive\Router\Attributes\Where;
use Tests\Fixtures\Models\User;

#[Middleware('bindings')]
class UserController extends AuthenticatedController
{
    public function index(): void {}

    #[Where('user', '[0-9]+')]
    public function show(User $user): void {}

    #[Put('update/{user}/{section}')]
    #[Middleware('verified')]
    public function update(User $user, string $section): void {}
}
