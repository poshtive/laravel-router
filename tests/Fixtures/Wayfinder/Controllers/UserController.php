<?php

namespace Tests\Fixtures\Wayfinder\Controllers;

use Poshtive\Router\Attributes\Route;
use Tests\Fixtures\Wayfinder\Enums\Status;
use Tests\Fixtures\Wayfinder\Models\Team;

class UserController
{
    public function index(): void {}

    public function show(Team $team): void {}

    public function search(?string $query = null): void {}

    #[Route(method: ['PUT', 'PATCH'])]
    public function update(Team $team, Status $status): void {}
}
