<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

enum Provenance: string
{
    case Convention = 'convention';
    case Strategy = 'strategy';
    case Group = 'group';
    case Inherited = 'inherited';
    case ClassAttribute = 'class_attribute';
    case MethodAttribute = 'method_attribute';
}
