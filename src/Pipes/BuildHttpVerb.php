<?php

namespace Poshtive\Router\Pipes;

use Closure;
use Illuminate\Support\Str;

class BuildHttpVerb
{
    public function handle(array $definitions, Closure $next)
    {
        $map = \config('router.http_methods_map', []);
        $convention = \config('router.convention', 'prefix');
        $verbs = ['get', 'post', 'put', 'patch', 'delete', 'options'];

        foreach ($definitions as $definition) {
            if (! empty($definition->httpVerb)) {
                continue;
            }

            $methodName = $definition->method->getName();

            if ($convention === 'prefix') {
                foreach ($verbs as $verb) {
                    if (Str::startsWith($methodName, $verb)) {
                        $definition->httpVerb = strtoupper($verb);
                        break;
                    }
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
