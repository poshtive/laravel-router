<?php

namespace Tests\Fixtures\Rejected\Controllers;

use Poshtive\Router\Attributes\ScopeBindings;
use Poshtive\Router\Attributes\WithoutScopedBindings;

class ScopeConflictController
{
    #[ScopeBindings]
    #[WithoutScopedBindings]
    public function show(): void {}
}
