<?php

namespace Tests\Fixtures\OptionalNested\Controllers\User;

use Tests\Fixtures\Models\User;

class ProfileController
{
    public function show(?User $user = null): void {}
}
