<?php

namespace Tests\Fixtures\Rejected\Controllers;

use Poshtive\Router\Attributes\Patch;
use Poshtive\Router\Attributes\Put;

class ConflictingVerbController
{
    #[Put('first')]
    #[Patch('second')]
    public function uri(): void {}

    #[Put(name: 'first')]
    #[Patch(name: 'second')]
    public function name(): void {}
}
