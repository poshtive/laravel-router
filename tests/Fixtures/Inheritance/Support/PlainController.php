<?php

namespace Tests\Fixtures\Inheritance\Support;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

abstract class PlainController extends Controller
{
    use AuthorizesRequests;
    use ValidatesRequests;

    public function shared(): void {}
}
