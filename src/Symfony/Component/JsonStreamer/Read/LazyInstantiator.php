<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Read;

use Symfony\Component\JsonStreamer\Exception\RuntimeException;

/**
 * Instantiates a new $className lazy ghost.
 *
 * The $initializer must be a callable that sets the actual object values when being called.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class LazyInstantiator
{
    /**
     * @var array{reflection: array<class-string, \ReflectionClass<object>>}
     */
    private static array $cache = [
        'reflection' => [],
    ];

    /**
     * @template T of object
     *
     * @param class-string<T>   $className
     * @param callable(T): void $initializer
     *
     * @return T
     */
    public function instantiate(string $className, callable $initializer): object
    {
        try {
            $classReflection = self::$cache['reflection'][$className] ??= new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        if ($classReflection->isInternal()) {
            $instance = $classReflection->newInstanceWithoutConstructor();
            $initializer($instance);

            return $instance;
        }

        return $classReflection->newLazyGhost($initializer);
    }
}
