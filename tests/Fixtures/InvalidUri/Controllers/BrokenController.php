<?php

namespace Tests\Fixtures\InvalidUri\Controllers;

use Poshtive\Router\Attributes\Route;
use Tests\Fixtures\Models\User;

class BrokenController
{
    #[Route(uri: 'items/{item')]
    public function show(User $item): void {}
}
