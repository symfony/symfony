<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Mapping\Loader;

use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * Provides methods to detect accessor name collisions during serialization.
 *
 * @internal
 */
trait AccessorCollisionResolverTrait
{
    private function getAttributeNameFromAccessor(\ReflectionClass $class, \ReflectionMethod $method, bool $andMutator): ?string
    {
        $methodName = $method->name;

        $i = match ($methodName[0]) {
            's' => $andMutator && str_starts_with($methodName, 'set') ? 3 : null,
            'g' => str_starts_with($methodName, 'get') ? 3 : null,
            'h' => str_starts_with($methodName, 'has') ? 3 : null,
            'c' => str_starts_with($methodName, 'can') ? 3 : null,
            'i' => str_starts_with($methodName, 'is') ? 2 : null,
            default => null,
        };

        // ctype_lower check to find out if method looks like accessor but actually is not, e.g. hash, cancel
        if (null === $i || ctype_lower($methodName[$i] ?? 'a') || (!$andMutator && $method->isStatic())) {
            return null;
        }

        if ('s' === $methodName[0] ? !$method->getNumberOfParameters() : ($method->getNumberOfRequiredParameters() || \in_array((string) $method->getReturnType(), ['void', 'never'], true))) {
            return null;
        }

        $attributeName = substr($methodName, $i);

        if (!$class->hasProperty($attributeName)) {
            $attributeName = lcfirst($attributeName);
        }

        return $attributeName;
    }

    private function hasPropertyForAccessor(\ReflectionClass $class, string $propName): bool
    {
        do {
            if ($class->hasProperty($propName)) {
                return true;
            }
        } while ($class = $class->getParentClass());

        return false;
    }

    private function hasAttributeNameCollision(\ReflectionClass $class, string $attributeName, string $methodName): bool
    {
        if ($this->hasPropertyForAccessor($class, $attributeName)) {
            return true;
        }

        if ($class->hasMethod($attributeName)) {
            $candidate = $class->getMethod($attributeName);
            if ($candidate->getName() !== $methodName && $this->isReadableAccessorMethod($candidate)) {
                return true;
            }
        }

        $ucAttributeName = ucfirst($attributeName);
        foreach (['get', 'is', 'has', 'can'] as $prefix) {
            $candidateName = $prefix.$ucAttributeName;
            if ($candidateName === $methodName || !$class->hasMethod($candidateName)) {
                continue;
            }

            if ($this->isReadableAccessorMethod($class->getMethod($candidateName))) {
                return true;
            }
        }

        return false;
    }

    private function isReadableAccessorMethod(\ReflectionMethod $method): bool
    {
        return $method->isPublic()
            && !$method->isStatic()
            && !$method->getAttributes(Ignore::class)
            && !$method->getNumberOfRequiredParameters()
            && !\in_array((string) $method->getReturnType(), ['void', 'never'], true);
    }
}
