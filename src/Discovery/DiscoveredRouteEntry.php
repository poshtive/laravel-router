<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

use JsonSerializable;
use Poshtive\Router\RouteDefinition;

final readonly class DiscoveredRouteEntry implements JsonSerializable
{
    /** @param list<string> $methods */
    /** @param array<string, string> $wheres */
    /** @param list<string> $middleware */
    /** @param list<string> $provenance */
    public function __construct(
        public string $id,
        public string $group,
        public RouteStatus $status,
        public array $methods,
        public string $uri,
        public string $name,
        public ?string $domain,
        public string $controller,
        public string $method,
        public string $sourceFile,
        public ?int $sourceLine,
        public array $middleware,
        public array $wheres,
        public bool $scopeBindings,
        public bool $withoutScopedBindings,
        public ?string $skipReason,
        public ?string $invalidReason,
        public ?string $discardReason,
        public array $provenance,
    ) {}

    /** @param list<string> $provenance */
    public static function fromRouteDefinition(
        RouteDefinition $def,
        RouteStatus $status,
        string $group,
        ?string $discardReason = null,
        array $provenance = [],
    ): self {
        $sourceLine = $def->method->getStartLine() ?: null;

        $sourceFile = self::relativePath((string) $def->file->getRealPath());

        return new self(
            id: hash('xxh32', "{$group}\0{$def->fullyQualifiedClassName}\0{$def->method->getName()}"),
            group: $group,
            status: $status,
            methods: $def->getEffectiveHttpVerbs(),
            uri: $def->getRegisteredUri(),
            name: $def->name,
            domain: $def->domain,
            controller: $def->fullyQualifiedClassName,
            method: $def->method->getName(),
            sourceFile: $sourceFile,
            sourceLine: $sourceLine,
            middleware: $def->middleware,
            wheres: $def->wheres,
            scopeBindings: $def->scopeBindings,
            withoutScopedBindings: $def->withoutScopedBindings,
            skipReason: $def->skipReason,
            invalidReason: $def->invalidReason,
            discardReason: $discardReason,
            provenance: $provenance,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'group' => $this->group,
            'status' => $this->status->value,
            'methods' => $this->methods,
            'uri' => $this->uri,
            'name' => $this->name,
            'domain' => $this->domain,
            'controller' => $this->controller,
            'method' => $this->method,
            'source_file' => $this->sourceFile,
            'source_line' => $this->sourceLine,
            'middleware' => $this->middleware,
            'wheres' => $this->wheres,
            'scope_bindings' => $this->scopeBindings,
            'without_scoped_bindings' => $this->withoutScopedBindings,
            'skip_reason' => $this->skipReason,
            'invalid_reason' => $this->invalidReason,
            'discard_reason' => $this->discardReason,
            'provenance' => $this->provenance,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function relativePath(string $absolutePath): string
    {
        $basePath = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        if ($basePath !== DIRECTORY_SEPARATOR && str_starts_with($absolutePath, $basePath)) {
            return substr($absolutePath, strlen($basePath));
        }

        return $absolutePath;
    }
}
