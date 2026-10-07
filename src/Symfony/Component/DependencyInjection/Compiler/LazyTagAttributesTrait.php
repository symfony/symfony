<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Resolves the tag attribute-sets that are computed from the class-string they are attached to.
 *
 * @internal
 */
trait LazyTagAttributesTrait
{
    /**
     * Whether a tag attribute-set is a lazily-computed one to be resolved per concrete class: either a [\Closure] (a closure wrapped in an array so it survives addTag()) or a [class-string, method] callable.
     */
    private static function isLazyTagAttributes(mixed $attributes): bool
    {
        if (!\is_array($attributes)) {
            return false;
        }

        if ([0] === array_keys($attributes) && $attributes[0] instanceof \Closure) {
            return true;
        }

        return [0, 1] === array_keys($attributes)
            && \is_string($attributes[0])
            && \is_string($attributes[1])
            && (class_exists($attributes[0]) || interface_exists($attributes[0]));
    }

    /**
     * Computes the attributes of a tag whose attribute-set is a wrapped closure or a [class-string, method] callable.
     *
     * @param string $declaredOn What carries the tag, an interface or a method, used in error messages
     */
    private static function resolveTagAttributes(array $attributes, string $class, string $tag, string $declaredOn): array
    {
        if ($attributes[0] instanceof \Closure) {
            $resolved = $attributes[0]($class);
            $source = \sprintf('The closure passed to the "%s" tag of "%s"', $tag, $declaredOn);
        } else {
            [$declaringClass, $method] = $attributes;
            if (!is_a($class, $declaringClass, true)) {
                throw new InvalidArgumentException(\sprintf('Cannot tag "%s" through "%s::%s()" because it is not a subtype of "%s".', $class, $declaringClass, $method, $declaringClass));
            }
            if (!method_exists($class, $method)) {
                throw new InvalidArgumentException(\sprintf('Cannot tag "%s" through "%s::%s()" because that method does not exist.', $class, $declaringClass, $method));
            }
            $resolved = $class::$method();
            $source = \sprintf('The "%s::%s()" method computing the "%s" tag attributes', $declaringClass, $method, $tag);
        }

        if (!\is_array($resolved)) {
            throw new InvalidArgumentException($source.\sprintf(' must return an array of attributes, "%s" returned.', get_debug_type($resolved)));
        }

        return $resolved;
    }

    /**
     * Whether lazily-computed attributes can be resolved for a class.
     *
     * Being instantiable is not required (private constructors and enums are fine), but a closure cannot run on an abstract class or an interface, and a [class-string, method] callable cannot when the method resolves to an abstract declaration there.
     * Skipping beats throwing because base types legitimately match the instanceof rules.
     * Interfaces need their own check: ReflectionClass::isAbstract() misses those declaring no methods.
     */
    private static function canComputeTagAttributes(\ReflectionClass $r, array $attributes): bool
    {
        if ($r->isInterface()) {
            return false;
        }

        if ($attributes[0] instanceof \Closure) {
            return !$r->isAbstract();
        }

        return !$r->hasMethod($attributes[1]) || !$r->getMethod($attributes[1])->isAbstract();
    }
}
