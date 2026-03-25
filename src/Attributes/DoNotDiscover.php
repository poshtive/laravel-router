<?php

namespace Poshtive\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class DoNotDiscover implements DiscoveryAttribute {}
