<?php

namespace Tests\Fixtures\Inheritance\Support;

use Poshtive\Router\Attributes\Discoverable;
use Poshtive\Router\Attributes\DoNotDiscover;
use Poshtive\Router\Attributes\Middleware;

#[Discoverable]
#[Middleware('auth')]
abstract class CrudController extends PlainController
{
    public function index(): string
    {
        return static::class.'@index';
    }

    public function store(): void {}

    public function show(string $id): void {}

    public function destroy(string $id): void {}

    #[DoNotDiscover]
    public function helper(): void {}
}
