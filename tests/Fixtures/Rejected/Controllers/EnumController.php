<?php

namespace Tests\Fixtures\Rejected\Controllers;

use Tests\Fixtures\Attributes\Support\IntStatus;
use Tests\Fixtures\Attributes\Support\UnitStatus;

class EnumController
{
    public function intStatus(IntStatus $status): void {}

    public function unitStatus(UnitStatus $status): void {}
}
