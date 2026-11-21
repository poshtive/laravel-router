<?php

namespace Tests\Fixtures\Attributes\Support;

use Illuminate\Routing\Controller;
use Poshtive\Router\Attributes\Middleware;
use Poshtive\Router\Attributes\Where;

#[Middleware('base')]
#[Middleware('base-2')]
#[Where('account', '[0-9]+')]
#[Where('team', '[A-Z]+')]
abstract class BaseController extends Controller {}
