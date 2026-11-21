<?php

namespace Tests\Fixtures\Inheritance\Controllers;

use Poshtive\Router\Attributes\Except;
use Tests\Fixtures\Inheritance\Support\CrudController;
use Tests\Fixtures\Inheritance\Support\ExportsCsv;
use Tests\Fixtures\Inheritance\Support\FormatsOutput;

#[Except('destroy')]
class BukuController extends CrudController
{
    use ExportsCsv;
    use FormatsOutput;

    public function terbitkan(string $id): void {}
}
