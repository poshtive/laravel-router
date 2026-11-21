<?php

namespace Tests\Fixtures\Conflicts\Controllers\Admin;

use Poshtive\Router\Attributes\Get;

class ConflictController
{
    #[Get('conflict')]
    public function index(): void {}
}
