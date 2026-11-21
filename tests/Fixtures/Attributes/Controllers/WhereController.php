<?php

namespace Tests\Fixtures\Attributes\Controllers;

use Poshtive\Router\Attributes\Where;
use Tests\Fixtures\Attributes\Support\BaseController;

#[Where('account', '[a-z]+')]
#[Where('region', '[a-z]{2}')]
class WhereController extends BaseController
{
    #[Where('member', '[0-9]+')]
    #[Where('region', '[A-Z]{2}')]
    public function show(): void {}
}
