<?php

declare(strict_types=1);

namespace Poshtive\Router\Support;

/**
 * Separator- and platform-agnostic path helpers.
 *
 * Laravel helpers such as app_path('Http/Controllers') produce mixed separators
 * on Windows (C:\app\Http/Controllers), while realpath() and Finder use "\".
 * Every comparison between absolute paths must go through this class.
 *
 * @internal
 */
final class Path
{
    /**
     * Normalize separators to "/", resolve "." and ".." segments, and drop
     * duplicate and trailing separators. Roots ("/", "C:/", "//server") are kept.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $root = '';
        if (preg_match('#^[A-Za-z]:(/|$)#', $path) === 1) {
            $root = strtoupper($path[0]).':/';
            $path = substr($path, 2);
        } elseif (str_starts_with($path, '//')) {
            $root = '//';
        } elseif (str_starts_with($path, '/')) {
            $root = '/';
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' && $segments !== [] && end($segments) !== '..') {
                array_pop($segments);

                continue;
            }

            if ($segment === '..' && $root !== '') {
                continue;
            }

            $segments[] = $segment;
        }

        return $root.implode('/', $segments);
    }

    /**
     * Return $path relative to $base using "/" separators, or null when $path
     * is not inside $base. Existing paths are resolved through realpath() first
     * so symlinks and ".." segments compare reliably.
     */
    public static function relative(string $base, string $path, ?bool $caseInsensitive = null): ?string
    {
        $caseInsensitive ??= PHP_OS_FAMILY === 'Windows';

        $base = self::normalize(self::resolve($base));
        $path = self::normalize(self::resolve($path));

        $prefix = str_ends_with($base, '/') ? $base : $base.'/';
        $compare = $caseInsensitive ? 'strtolower' : static fn (string $value): string => $value;

        if ($compare($path) === $compare($base)) {
            return '';
        }

        if (! str_starts_with($compare($path), $compare($prefix))) {
            return null;
        }

        return substr($path, strlen($prefix));
    }

    /**
     * Convert a relative file or directory path into a namespace fragment.
     */
    public static function toNamespace(string $relativePath): string
    {
        $relativePath = preg_replace('/\.php$/', '', $relativePath) ?? $relativePath;

        return trim(str_replace(['/', '\\'], '\\', $relativePath), '\\');
    }

    private static function resolve(string $path): string
    {
        $real = $path === '' ? false : realpath($path);

        return $real === false ? $path : $real;
    }
}
