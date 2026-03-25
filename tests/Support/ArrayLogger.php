<?php

namespace Tests\Support;

class ArrayLogger
{
  public array $infoMessages = [];
  public array $warningMessages = [];

  public function info(string $message): void
  {
    $this->infoMessages[] = $message;
  }

  public function warning(string $message): void
  {
    $this->warningMessages[] = $message;
  }
}
