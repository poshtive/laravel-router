<?php

declare(strict_types=1);

namespace Poshtive\Router\Attributes;

use Attribute;

/**
 * Opt the public methods of a parent class or trait into discovery for every
 * controller that inherits or uses them. Unmarked parents and traits are never
 * scanned, so framework helpers such as authorize() or validate() never become
 * routes.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Discoverable {}
