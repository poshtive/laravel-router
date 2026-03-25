<?php

namespace Poshtive\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class IgnoreParentMiddleware implements DiscoveryAttribute {}
