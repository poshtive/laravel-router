<?php

namespace Tests\Fixtures\RouteDiscovery\Controllers;

use Illuminate\Routing\Controller;
use Poshtive\Router\Attributes\Middleware;

#[Middleware('auth')]
abstract class AuthenticatedController extends Controller {}
