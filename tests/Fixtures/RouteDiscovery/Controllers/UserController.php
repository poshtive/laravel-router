<?php

namespace Tests\Fixtures\RouteDiscovery\Controllers;

use Poshtive\Router\Attributes\Route;
use Poshtive\Router\Attributes\Where;
use Tests\Fixtures\Models\User;

#[Route(middleware: ['bindings'])]
class UserController extends AuthenticatedController
{
    public function index(): void {}

    #[Where('user', '[0-9]+')]
    public function show(User $user): void {}

    #[Route(method: 'PUT', middleware: ['verified'], keepOrder: true)]
    public function update(User $user, string $section): void {}
}
