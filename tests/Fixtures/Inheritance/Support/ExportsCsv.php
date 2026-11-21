<?php

namespace Tests\Fixtures\Inheritance\Support;

use Poshtive\Router\Attributes\Discoverable;

#[Discoverable]
trait ExportsCsv
{
    public function export(): void {}
}
