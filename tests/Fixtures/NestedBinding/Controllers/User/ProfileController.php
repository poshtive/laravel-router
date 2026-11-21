<?php

namespace Tests\Fixtures\NestedBinding\Controllers\User;

use Poshtive\Router\Attributes\Get;
use Tests\Fixtures\Models\User;

class ProfileController
{
    #[Get('settings')]
    public function editProfile(User $user, User $profile): void {}
}
