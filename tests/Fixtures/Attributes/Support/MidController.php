<?php

namespace Tests\Fixtures\Attributes\Support;

use Poshtive\Router\Attributes\Middleware;

#[Middleware('mid')]
abstract class MidController extends BaseController {}
