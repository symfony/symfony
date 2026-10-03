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
 * @internal
 */
class PriorityTaggedServiceUtil
{
    /**
     * @param array<array{0: int, 1: int, 2: string|null, 3: string, 4: string|null, 5: array, 6: int|null}> $services
     * @param array<string, string|null>                                                                     $classById
     *
     * @return list<array{0: int, 1: int, 2: string|null, 3: string, 4: string|null, 5: array, 6: int|null}>
     */
    public static function applyConstraints(array $services, array $classById, string $tagName): array
    {
        $entries = [];
        $priorities = [];
        $aliases = [];
        $keysById = [];
        $constraints = [];

        // each tag gets its own key so that the other tags of a service keep the place the priority sort gave them
        foreach ($services as $service) {
            [, , , $serviceId, , $serviceConstraints, $priority] = $service;

            for ($n = 0, $key = $serviceId; isset($entries[$key]); ++$n) {
                $key = $serviceId.'#'.$n;
            }

            $entries[$key] = $service;
            $priorities[$key] = $priority;
            $keysById[$serviceId][] = $key;

            if ($serviceConstraints) {
                $constraints[$key] = $serviceConstraints;
            }

            if (null !== $class = $classById[$serviceId] ?? null) {
                $aliases[$class][] = $key;
            }
        }

        // a service id always designates its own tags, whatever class it happens to share a name with
        $aliases = $keysById + $aliases;

        try {
            $keys = array_keys(BeforeAfterSorter::sortWithPriorities($priorities, $constraints, $aliases));
        } catch (InvalidArgumentException $e) {
            throw new InvalidArgumentException(\sprintf('Invalid "before"/"after" constraints on tag "%s": ', $tagName).lcfirst($e->getMessage()), previous: $e);
        }

        $sorted = [];

        foreach ($keys as $key) {
            $sorted[] = $entries[$key];
        }

        return $sorted;
    }

    public static function getDefault(string $serviceId, \ReflectionClass $r, string $defaultMethod, string $tagName, ?string $indexAttribute): string|int|null
    {
        if ($r->isInterface() || !$r->hasMethod($defaultMethod)) {
            return null;
        }

        $class = $r->name;

        if (null !== $indexAttribute) {
            $service = $class !== $serviceId ? \sprintf('service "%s"', $serviceId) : 'on the corresponding service';
            $message = [\sprintf('Either method "%s::%s()" should ', $class, $defaultMethod), \sprintf(' or tag "%s" on %s is missing attribute "%s".', $tagName, $service, $indexAttribute)];
        } else {
            $message = [\sprintf('Method "%s::%s()" should ', $class, $defaultMethod), '.'];
        }

        if (!($rm = $r->getMethod($defaultMethod))->isStatic()) {
            throw new InvalidArgumentException(implode('be static', $message));
        }

        if (!$rm->isPublic()) {
            throw new InvalidArgumentException(implode('be public', $message));
        }

        trigger_deprecation('symfony/dependency-injection', '8.1', 'Calling "%s::%s()" to get the "%s" index is deprecated, use the #[AsTaggedItem] attribute instead.', $class, $defaultMethod, $indexAttribute);

        $default = $rm->invoke(null);

        if ('priority' === $indexAttribute) {
            if (!\is_int($default)) {
                throw new InvalidArgumentException(implode(\sprintf('return int (got "%s")', get_debug_type($default)), $message));
            }

            return $default;
        }

        if (\is_int($default)) {
            $default = (string) $default;
        }

        if (!\is_string($default)) {
            throw new InvalidArgumentException(implode(\sprintf('return string|int (got "%s")', get_debug_type($default)), $message));
        }

        return $default;
    }
}
