<?php

namespace Tests\Fixtures\Singleton\Controllers\Device;

use Tests\Fixtures\Models\User as Device;

class PairingController
{
    public function show(Device $device): void {}

    public function store(Device $device): void {}

    public function destroy(Device $device): void {}
}
