<?php

namespace Tests\Fixtures\ActionFilterTypo\Controllers;

use Poshtive\Router\Attributes\Except;

#[Except('destory')]
class ArtikelController
{
    public function index(): void {}

    public function destroy(string $id): void {}
}
