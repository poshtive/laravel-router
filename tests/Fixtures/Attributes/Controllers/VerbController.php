<?php

namespace Tests\Fixtures\Attributes\Controllers;

use Poshtive\Router\Attributes\Delete;
use Poshtive\Router\Attributes\Get;
use Poshtive\Router\Attributes\Patch;
use Poshtive\Router\Attributes\Post;
use Poshtive\Router\Attributes\Put;
use Tests\Fixtures\Attributes\Support\Status;
use Tests\Fixtures\Models\User;

class VerbController
{
    #[Get]
    public function viewed(): void {}

    #[Post]
    public function created(): void {}

    #[Put]
    public function replaced(): void {}

    #[Patch]
    public function patched(): void {}

    #[Delete]
    public function deleted(): void {}

    #[Post]
    #[Post]
    public function duplicated(): void {}

    #[Patch('custom-uri', name: 'custom.multi')]
    #[Delete]
    public function multi(): void {}

    #[Get('/teams/{team}/members/{member}')]
    public function absolute(string $team, string $member): void {}

    #[Get('users/{user:slug}')]
    public function customKey(User $user): void {}

    public function status(Status $status): void {}

    public function optional(?int $id = null): void {}

    public function showAccount(): void {}
}
