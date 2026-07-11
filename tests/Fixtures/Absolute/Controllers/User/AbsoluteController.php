<?php

namespace Tests\Fixtures\Absolute\Controllers\User;

use Poshtive\Router\Attributes\Route;
use Tests\Fixtures\Models\User;

#[Route(uri: '/teams/{team}/members/{member}/settings', absolute: true)]
class AbsoluteController
{
    public function index(User $team, User $member): void {}
}
