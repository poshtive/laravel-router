<?php

namespace Tests\Support;

class ArrayLogger
{
    public array $infoMessages = [];

    public array $warningMessages = [];

    public array $warningContexts = [];

    public function info(string $message): void
    {
        $this->infoMessages[] = $message;
    }

    public function warning(string $message, array $context = []): void
    {
        $this->warningMessages[] = $message;
        $this->warningContexts[] = $context;
    }
}
