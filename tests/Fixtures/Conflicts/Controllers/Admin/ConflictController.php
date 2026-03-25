<?php

namespace Tests\Fixtures\Conflicts\Controllers\Admin;

use Poshtive\Router\Attributes\Route;

class ConflictController
{
  #[Route(uri: 'conflict', method: 'GET')]
  public function index(): void {}
}
