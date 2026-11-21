<?php

namespace Tests\Fixtures\DeepNesting\Controllers\User\Post;

class CommentController
{
    public function index(string $user, string $post): void {}

    public function show(string $user, string $post, string $comment): void {}
}
