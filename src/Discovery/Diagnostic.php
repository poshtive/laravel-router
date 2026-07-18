<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

use JsonSerializable;

final readonly class Diagnostic implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $group,
        public string $path,
        public string $message,
    ) {}

    public function __toString(): string
    {
        return sprintf('[%s] %s: %s', $this->severity, $this->group, $this->message);
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'severity' => $this->severity,
            'group' => $this->group,
            'path' => $this->path,
            'message' => $this->message,
        ];
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
