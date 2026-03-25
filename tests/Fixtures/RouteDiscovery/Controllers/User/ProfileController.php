<?php

namespace Tests\Fixtures\RouteDiscovery\Controllers\User;

use Tests\Fixtures\Models\User;

class ProfileController
{
    public function edit(User $user, string $section): void {}
}
