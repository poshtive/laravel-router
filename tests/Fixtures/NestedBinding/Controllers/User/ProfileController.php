<?php

namespace Tests\Fixtures\NestedBinding\Controllers\User;

use Poshtive\Router\Attributes\Route;
use Tests\Fixtures\Models\User;

class ProfileController
{
    #[Route(uri: 'settings')]
    public function editProfile(User $user, User $profile): void {}
}
