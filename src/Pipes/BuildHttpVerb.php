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
            if (! $definition->isDiscoverable) {
                continue;
            }

            if (! empty($definition->httpVerb)) {
                continue;
            }

            $methodName = $definition->method->getName();

            if ($convention === 'prefix') {
                $verb = RouteDefinition::httpVerbPrefixFor($methodName);
                if ($verb !== null) {
                    $definition->httpVerb = strtoupper($verb);
                } else {
                    $definition->httpVerb = '';
                    $definition->fallbackHttpVerb = 'GET';
                    $definition->isFallbackVerb = true;
                    $definition->isDiscoverable = false;
                    $definition->skipReason = sprintf(
                        'Skipped %s because [%s] does not match the prefix routing convention; using fallback GET.',
                        $definition->descriptor(), $methodName
                    );
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
