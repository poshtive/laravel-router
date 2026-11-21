<?php

namespace Tests\Fixtures\Resource\Controllers;

use Poshtive\Router\Attributes\Post;

class BukuController
{
    public function index(): string
    {
        return 'index';
    }

    public function create(): string
    {
        return 'create';
    }

    public function store(): string
    {
        return 'store';
    }

    public function show(string $buku): string
    {
        return 'show '.$buku;
    }

    public function edit(string $buku): string
    {
        return 'edit '.$buku;
    }

    public function update(string $buku): string
    {
        return 'update '.$buku;
    }

    public function destroy(string $buku): string
    {
        return 'destroy '.$buku;
    }

    #[Post]
    public function terbitkan(string $buku): string
    {
        return 'terbitkan '.$buku;
    }
}
