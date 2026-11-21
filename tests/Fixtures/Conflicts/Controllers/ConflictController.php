<?php

namespace Tests\Fixtures\Conflicts\Controllers;

use Poshtive\Router\Attributes\Get;

class ConflictController
{
    #[Get('conflict')]
    public function index(): void {}
}
