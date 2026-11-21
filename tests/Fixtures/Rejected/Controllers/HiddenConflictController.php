<?php

namespace Tests\Fixtures\Rejected\Controllers;

use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Patch;
use Poshtive\Router\Attributes\Put;
use Poshtive\Router\Attributes\ScopeBindings;
use Poshtive\Router\Attributes\WithoutScopedBindings;

#[DoNotDiscover]
class HiddenConflictController
{
    #[Put('first')]
    #[Patch('second')]
    #[ScopeBindings]
    #[WithoutScopedBindings]
    public function update(): void {}
}
