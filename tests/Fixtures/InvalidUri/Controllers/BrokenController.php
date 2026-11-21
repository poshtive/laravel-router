<?php

namespace Tests\Fixtures\InvalidUri\Controllers;

use Poshtive\Router\Attributes\Get;
use Tests\Fixtures\Models\User;

class BrokenController
{
    #[Get('items/{item')]
    public function show(User $item): void {}
}
