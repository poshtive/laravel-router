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
            $classpath = implode('.', array_map(fn ($part) => Str::kebab(Str::studly($part)), explode('.', $classpath)));
            $method = $definition->getMethodName();
            $classpath = Str::replaceStart('index.', '', strtolower($classpath));
            if ($definition->className !== null) {
                $segments = explode('.', $classpath);
                $segments[count($segments) - 1] = Str::kebab($definition->className);
                $classpath = implode('.', $segments);
            }
            $definition->name = $definition->methodNameOverride
                ?? Str::replaceStart('index.', '', strtolower("{$classpath}.{$method}"));
        }

        return $next($definitions);
    }
}
