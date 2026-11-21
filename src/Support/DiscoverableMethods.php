<?php

declare(strict_types=1);

namespace Poshtive\Router\Support;

use Poshtive\Router\Attributes\Discoverable;
use ReflectionClass;
use ReflectionMethod;

/**
 * Select the public methods of a controller that may become routes.
 *
 * A method qualifies when it is written in the controller itself, or in a
 * parent class or trait marked #[Discoverable].
 *
 * @internal
 */
final class DiscoverableMethods
{
    /**
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionMethod>
     */
    public static function of(ReflectionClass $class): array
    {
        $methods = [];

        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic() || $method->isConstructor() || $method->isDestructor() || str_starts_with($method->getName(), '__')) {
                continue;
            }

            $origin = self::origin($method);

            if ($origin->getName() === $class->getName() || $origin->getAttributes(Discoverable::class) !== []) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * The class or trait whose source contains the method body. Reflection
     * reports trait methods as declared by the using class, so compare source
     * locations instead.
     *
     * @return ReflectionClass<object>
     */
    private static function origin(ReflectionMethod $method): ReflectionClass
    {
        $declaring = $method->getDeclaringClass();

        foreach (self::traits($declaring) as $trait) {
            if ($method->getFileName() === $trait->getFileName()
                && $method->getStartLine() >= $trait->getStartLine()
                && $method->getEndLine() <= $trait->getEndLine()) {
                return $trait;
            }
        }

        return $declaring;
    }

    /**
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionClass<object>>
     */
    private static function traits(ReflectionClass $class): array
    {
        $traits = [];

        foreach ($class->getTraits() as $trait) {
            $traits[] = $trait;
            array_push($traits, ...self::traits($trait));
        }

        return $traits;
    }
}
