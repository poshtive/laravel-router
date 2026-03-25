<?php

namespace Tests\Fixtures\Diagnostics\Controllers;

use Poshtive\Router\Attributes\DoNotDiscover;

#[DoNotDiscover]
class HiddenController
{
  public function index(): void {}
}
