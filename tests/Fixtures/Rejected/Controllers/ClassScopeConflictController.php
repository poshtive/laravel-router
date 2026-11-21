<?php

namespace Tests\Fixtures\Rejected\Controllers;

use Poshtive\Router\Attributes\ScopeBindings;
use Poshtive\Router\Attributes\WithoutScopedBindings;

#[ScopeBindings]
#[WithoutScopedBindings]
class ClassScopeConflictController
{
    public function show(): void {}
}
