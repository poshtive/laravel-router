<?php

declare(strict_types=1);

namespace Poshtive\Router\Support;

/**
 * Laravel resource controller conventions.
 *
 * | Action  | Verb       | URI                   |
 * | ------- | ---------- | --------------------- |
 * | index   | GET        | /photos               |
 * | create  | GET        | /photos/create        |
 * | store   | POST       | /photos               |
 * | show    | GET        | /photos/{photo}       |
 * | edit    | GET        | /photos/{photo}/edit  |
 * | update  | PUT, PATCH | /photos/{photo}       |
 * | destroy | DELETE     | /photos/{photo}       |
 *
 * Any other public method is a GET action whose name becomes a URI segment.
 *
 * @internal
 */
final class ResourceActions
{
    private const array VERBS = [
        'store' => ['POST'],
        'update' => ['PUT', 'PATCH'],
        'destroy' => ['DELETE'],
    ];

    private const array WITHOUT_SEGMENT = ['index', 'store', 'show', 'update', 'destroy'];

    /** @return list<string> */
    public static function verbsFor(string $method): array
    {
        return self::VERBS[$method] ?? ['GET'];
    }

    public static function hasUriSegment(string $method): bool
    {
        return ! in_array($method, self::WITHOUT_SEGMENT, true);
    }
}
