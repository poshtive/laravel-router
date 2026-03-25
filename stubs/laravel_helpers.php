<?php

if (!function_exists('app')) {
  function app(?string $abstract = null, array $parameters = []): mixed
  {
    return null;
  }
}

if (!function_exists('config')) {
  function config(?string $key = null, mixed $default = null): mixed
  {
    return $default;
  }
}

if (!function_exists('base_path')) {
  function base_path(string $path = ''): string
  {
    return $path;
  }
}

if (!function_exists('config_path')) {
  function config_path(string $path = ''): string
  {
    if ($path === '') {
      return 'config';
    }

    return 'config/' . ltrim($path, '/');
  }
}
