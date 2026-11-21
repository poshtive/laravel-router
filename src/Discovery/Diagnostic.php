<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

use JsonSerializable;

final readonly class Diagnostic implements JsonSerializable
{
    /**
     * @param  string  $code  Stable identifier, such as route_invalid.
     * @param  string  $path  Configured path or source file, when relevant.
     * @param  string|null  $action  Controller action as Class::method, when relevant.
     */
    public function __construct(
        public string $code,
        public Severity $severity,
        public string $message,
        public string $group = '',
        public string $path = '',
        public ?string $action = null,
    ) {}

    public function __toString(): string
    {
        return sprintf('[%s] %s', $this->severity->value, $this->message);
    }

    /** @return array{code: string, severity: string, message: string, group: string, path: string, action: string|null} */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'group' => $this->group,
            'path' => $this->path,
            'action' => $this->action,
        ];
    }

    /** @return array{code: string, severity: string, message: string, group: string, path: string, action: string|null} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
