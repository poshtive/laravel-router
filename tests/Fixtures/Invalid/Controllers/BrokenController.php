<?php

namespace Tests\Fixtures\Invalid\Controllers;

use Poshtive\Router\Attributes\Route;

class BrokenController
{
    #[Route(uri: 'items/{item}')]
    public function show(): void {}
}
