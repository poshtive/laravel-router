<?php

declare(strict_types=1);

namespace Poshtive\Router;

use Illuminate\Routing\Router;
use Poshtive\Router\Discovery\Diagnostic;
use Poshtive\Router\Discovery\GroupConfig;
use Poshtive\Router\Discovery\Severity;
use Poshtive\Router\Exceptions\RouteDiscoveryException;
use Poshtive\Router\Support\ControllerPath;
use Poshtive\Router\Support\Path;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

class RouteRegistrar
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    /** @param  bool  $local  Whether #[LocalOnly] routes are registered. */
    public function __construct(private Router $router, private bool $local) {}

    /**
     * Discover the controllers in one directory of a group, reporting every
     * action that is skipped or cannot be registered.
     *
     * @param  string  $namespace  The namespace of the classes directly inside the directory.
     * @return list<RouteDefinition> The actions that can be registered.
     */
    public function discover(GroupConfig $group, string $directory, string $namespace): array
    {
        $finder = (new Finder)->files()->in($directory)->name($group->patterns)->notName($group->notPatterns)->sortByName();
        $definitions = [];

        foreach ($finder as $file) {
            $path = ControllerPath::of($file, $group->pluralize);
            $className = $namespace.'\\'.Path::toNamespace($path->relative);

            if (! class_exists($className)) {
                $this->report('controller_unloadable', Severity::Warning, sprintf('Class [%s] could not be loaded from [%s].', $className, $path->relative), $group->name, $path->relative);

                continue;
            }

            $class = new ReflectionClass($className);
            if ($class->isAbstract()) {
                $this->report('controller_abstract', Severity::Info, sprintf('Skipped abstract controller [%s].', $className), $group->name, $path->relative);

                continue;
            }

            $controller = new ControllerRoutes($group, $class, $path, $this->local);

            foreach ($controller->unknownFilterEntries() as $warning) {
                $this->report('action_filter_unknown', Severity::Warning, $warning, $group->name, $path->relative);
            }

            foreach ($controller->definitions() as $definition) {
                if ($definition->skipReason !== null) {
                    $this->reportRoute($definition, 'route_skipped', Severity::Info, $definition->skipReason);
                } elseif ($definition->errors !== []) {
                    foreach ($definition->errors as $error) {
                        $this->reportRoute($definition, 'route_invalid', Severity::Error, $error);
                    }
                } else {
                    $definitions[] = $definition;
                }
            }
        }

        return $definitions;
    }

    /**
     * Deduplicate the definitions, then register the routes.
     *
     * Validation is global and atomic: in strict mode any error throws before a
     * single route is registered. Otherwise duplicate routes are skipped and
     * reported as diagnostics.
     *
     * @param  list<RouteDefinition>  $definitions
     */
    public function registerDefinitions(array $definitions, bool $strict = false): void
    {
        $definitions = $this->withoutDuplicates($definitions);

        $errors = array_values(array_filter(
            $this->diagnostics,
            fn (Diagnostic $diagnostic): bool => $diagnostic->severity === Severity::Error,
        ));

        if ($strict && $errors !== []) {
            throw new RouteDiscoveryException($errors);
        }

        $this->registerRoutes($definitions);
    }

    /** @return list<Diagnostic> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @param  list<RouteDefinition>  $definitions
     * @return list<RouteDefinition>
     */
    private function withoutDuplicates(array $definitions): array
    {
        /** @var array<string, RouteDefinition> $names */
        $names = [];
        /** @var array<string, RouteDefinition> $signatures */
        $signatures = [];
        $unique = [];

        foreach ($definitions as $definition) {
            $duplicate = false;

            if ($definition->name !== '' && isset($names[$definition->name])) {
                $duplicate = true;
                $this->reportRoute($definition, 'route_duplicate_name', Severity::Error, sprintf(
                    'Discovered duplicate route name [%s] for [%s] and [%s].',
                    $definition->name,
                    $names[$definition->name]->descriptor(),
                    $definition->descriptor(),
                ));
            }

            $effectiveSignatures = array_map(
                fn (string $verb): string => sprintf('%s %s %s', $definition->domain ?? '*', $verb, $definition->uri),
                $definition->effectiveVerbs(),
            );

            foreach ($effectiveSignatures as $signature) {
                if (isset($signatures[$signature])) {
                    $duplicate = true;
                    $this->reportRoute($definition, 'route_duplicate_signature', Severity::Error, sprintf(
                        'Discovered duplicate route signature [%s] for [%s] and [%s].',
                        $signature,
                        $signatures[$signature]->descriptor(),
                        $definition->descriptor(),
                    ));
                }
            }

            if ($duplicate) {
                continue;
            }

            if ($definition->name !== '') {
                $names[$definition->name] = $definition;
            }
            foreach ($effectiveSignatures as $signature) {
                $signatures[$signature] = $definition;
            }
            $unique[] = $definition;
        }

        return $unique;
    }

    /**
     * Register static URIs before ones with placeholders, so /photos/create
     * is matched before /photos/{photo}.
     *
     * @param  list<RouteDefinition>  $definitions
     */
    private function registerRoutes(array $definitions): void
    {
        $order = fn (RouteDefinition $definition): array => [
            substr_count($definition->uri, '{') * 1000 - strlen(ltrim($definition->uri, '/')),
            $definition->uri,
            $definition->name,
            $definition->controller,
            $definition->action,
        ];
        usort($definitions, fn (RouteDefinition $a, RouteDefinition $b): int => $order($a) <=> $order($b));

        foreach ($definitions as $definition) {
            $uses = $definition->controller.'@'.$definition->action;
            $route = $this->router->newRoute($definition->verbs, $definition->uri, [
                'uses' => $uses,
                'controller' => $uses,
                '_laravel_router' => ['group' => $definition->group],
            ]);

            if ($definition->domain !== null) {
                $route->domain($definition->domain);
            }

            $route->name($definition->name);

            if ($definition->middleware !== []) {
                $route->middleware($definition->middleware);
            }

            if ($definition->wheres !== []) {
                $route->setWheres($definition->wheres);
            }

            if ($definition->scopeBindings === true) {
                $route->scopeBindings();
            } elseif ($definition->scopeBindings === false) {
                $route->withoutScopedBindings();
            }

            $this->router->getRoutes()->add($route);
        }
    }

    private function reportRoute(RouteDefinition $definition, string $code, Severity $severity, string $message): void
    {
        $this->report($code, $severity, $message, $definition->group, $definition->path, $definition->descriptor());
    }

    private function report(string $code, Severity $severity, string $message, string $group, string $path, ?string $action = null): void
    {
        $this->diagnostics[] = new Diagnostic($code, $severity, $message, $group, $path, $action);
    }
}
