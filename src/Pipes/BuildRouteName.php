<?php

namespace Poshtive\Router\Pipes;

use Closure;
use Illuminate\Support\Str;

class BuildRouteName
{
    public function handle(array $definitions, Closure $next)
    {
        foreach ($definitions as $definition) {
            $classpath = str_replace(
                ['Controller.php', DIRECTORY_SEPARATOR],
                ['', '.'],
                $definition->file->getRelativePathname()
            );
            $classpath = implode('.', array_map(fn($part) => Str::kebab(Str::studly($part)), explode('.', $classpath)));
            $method = $definition->getMethodName();

            $definition->name = Str::replaceStart('index.', '', strtolower("{$classpath}.{$method}"));
        }

        return $next($definitions);
    }
}
