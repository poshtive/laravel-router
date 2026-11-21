<?php

namespace Tests\Fixtures\Absolute\Controllers\User;

use Poshtive\Router\Attributes\Resource;
use Tests\Fixtures\Models\User;

#[Resource('/teams/{team}/members/{member}/settings')]
class AbsoluteController
{
    public function index(User $team, User $member): void {}
}
