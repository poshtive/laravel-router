<?php

declare(strict_types=1);

namespace Poshtive\Router\Support;

use Illuminate\Support\Str;

/**
 * Turn a folder or controller name into a URI or route-name segment.
 *
 * Names are used as written unless the group opts into pluralization, which
 * uses Laravel's pluralizer (and therefore Pluralizer::useLanguage()).
 *
 * @internal
 */
final class ResourceSegment
{
    public static function uri(string $name, bool $pluralize): string
    {
        if (! $pluralize || strtolower($name) === 'index') {
            return $name;
        }

        return Str::pluralStudly(Str::studly($name));
    }

    public static function name(string $name, bool $pluralize): string
    {
        return Str::kebab(self::uri(Str::studly($name), $pluralize));
    }
}
