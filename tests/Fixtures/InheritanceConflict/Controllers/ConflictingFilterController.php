<?php

namespace Tests\Fixtures\InheritanceConflict\Controllers;

use Poshtive\Router\Attributes\Except;
use Poshtive\Router\Attributes\Only;

#[Only('index')]
#[Except('show')]
class ConflictingFilterController
{
    public function index(): void {}

    public function show(): void {}
}
