<?php

namespace Tests\Fixtures\Inheritance\Controllers;

use Poshtive\Router\Attributes\Only;
use Tests\Fixtures\Inheritance\Support\CrudController;

#[Only('index', 'profil')]
class PenulisController extends CrudController
{
    public function profil(string $id): void {}
}
