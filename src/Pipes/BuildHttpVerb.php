<?php

namespace Poshtive\Router\Pipes;

use Closure;
use Poshtive\Router\RouteDefinition;

class BuildHttpVerb
{
    public function handle(array $definitions, Closure $next)
    {
        $map = \config('router.http_methods_map', []);
        $convention = \config('router.convention', 'prefix');

        foreach ($definitions as $definition) {
            if (! empty($definition->httpVerb)) {
                continue;
            }

            $methodName = $definition->method->getName();

            if ($convention === 'prefix') {
                $verb = RouteDefinition::httpVerbPrefixFor($methodName);
                if ($verb !== null) {
                    $definition->httpVerb = strtoupper($verb);
                }
            } else {
                $definition->httpVerb = 'GET';
                if (isset($map[$methodName])) {
                    $mapped = $map[$methodName];
                    if (is_array($mapped)) {
                        $definition->httpVerb = array_map('strtoupper', $mapped);
                    } else {
                        $definition->httpVerb = strtoupper($mapped);
                    }
                }
            }
        }

        return $next($definitions);
    }
}
