<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

/**
 * One entry of config('router.groups'), resolved once before discovery.
 */
final readonly class GroupConfig
{
    /**
     * @param  list<mixed>  $paths
     * @param  list<string>  $middleware
     * @param  list<string>  $patterns
     * @param  list<string>  $notPatterns
     */
    public function __construct(
        public string $name,
        public array $paths = [],
        public string $prefix = '',
        public string $namePrefix = '',
        public array $middleware = [],
        public ?string $domain = null,
        public ?string $namespace = null,
        public array $patterns = ['*.php'],
        public array $notPatterns = [],
        public bool $pluralize = false,
    ) {}

    /** @param array<string, mixed> $options */
    public static function fromArray(string $name, array $options): self
    {
        $namespace = trim((string) ($options['namespace'] ?? ''), '\\');

        return new self(
            name: $name,
            paths: array_values((array) ($options['paths'] ?? [])),
            prefix: trim((string) ($options['prefix'] ?? ''), '/'),
            namePrefix: (string) ($options['name'] ?? ''),
            middleware: self::strings($options['middleware'] ?? []),
            domain: isset($options['domain']) ? (string) $options['domain'] : null,
            namespace: $namespace === '' ? null : $namespace,
            patterns: self::strings($options['patterns'] ?? ['*.php']),
            notPatterns: self::strings($options['not_patterns'] ?? []),
            pluralize: (bool) ($options['pluralize'] ?? false),
        );
    }

    /** @return list<string> */
    private static function strings(mixed $values): array
    {
        return array_values(array_map(strval(...), (array) $values));
    }
}
