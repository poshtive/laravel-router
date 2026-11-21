<?php

namespace Tests\Fixtures\Configured\Controllers\Account;

use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Post;
use Poshtive\Router\Attributes\Resource;
use Poshtive\Router\Attributes\ScopeBindings;
use Poshtive\Router\Attributes\WithoutScopedBindings;

#[Resource('profiles', name: 'profiles')]
#[ScopeBindings]
class ProfileController
{
    public function index(string $account): void {}

    #[Post('settings', name: 'account.settings')]
    #[WithoutScopedBindings]
    public function edit(string $account): void {}

    #[DoNotDiscover]
    public function helper(): void {}
}
