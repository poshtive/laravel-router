<?php

namespace Tests\Fixtures\Inheritance\Controllers;

use Tests\Fixtures\Inheritance\Support\PlainController;

class PlainChildController extends PlainController
{
    public function index(): void {}
}
