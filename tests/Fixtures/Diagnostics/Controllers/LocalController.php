<?php

namespace Tests\Fixtures\Diagnostics\Controllers;

use Poshtive\Router\Attributes\LocalOnly;

class LocalController
{
    #[LocalOnly]
    public function index(): void {}
}
