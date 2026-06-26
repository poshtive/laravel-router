<?php

namespace Tests\Fixtures\Registrar\Controllers;

class ConcreteController extends BaseController
{
    public function __construct() {}

    public function __destruct() {}

    public static function helper(): void {}

    public function __call(string $name, array $arguments): void {}

    public function show(): void {}
}
