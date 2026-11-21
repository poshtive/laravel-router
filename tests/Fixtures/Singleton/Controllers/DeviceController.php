<?php

namespace Tests\Fixtures\Singleton\Controllers;

use Tests\Fixtures\Models\User as Device;

class DeviceController
{
    public function show(Device $device): void {}
}
