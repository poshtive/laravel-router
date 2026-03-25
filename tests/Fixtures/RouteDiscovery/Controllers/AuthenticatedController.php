<?php

namespace Tests\Fixtures\RouteDiscovery\Controllers;

use Illuminate\Routing\Controller;
use Poshtive\Router\Attributes\Route;

#[Route(middleware: ['auth'])]
abstract class AuthenticatedController extends Controller {}
