<?php

namespace Tests\Fixtures\Configured\Controllers\Account;

use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Route;

#[Route(uri: 'profiles', name: 'profiles', scopeBindings: true)]
class ProfileController
{
    public function index(string $account): void {}

    #[Route(uri: 'settings', name: 'account.settings', method: 'POST', scopeBindings: true, withoutScopedBindings: true)]
    public function edit(string $account): void {}

    #[DoNotDiscover]
    public function helper(): void {}
}
