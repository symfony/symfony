<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Metadata;

/**
 * @internal
 *
 * @template T of object
 */
final class AttributeReader
{
    /**
     * @var array<string, array<class-string<T>, list<T>>>
     */
    private array $cache = [];

    /**
     * @param class-string    $className
     * @param class-string<T> $name
     *
     * @return list<T>
     */
    public function forClass(string $className, string $name): array
    {
        $attributes = $this->cache[$className] ??= $this->readAttributes(new \ReflectionClass($className));

        return $attributes[$name] ?? [];
    }

    /**
     * @param class-string    $className
     * @param class-string<T> $name
     *
     * @return list<T>
     */
    public function forMethod(string $className, string $methodName, string $name): array
    {
        $attributes = $this->cache[$className.'::'.$methodName] ??= $this->readAttributes(new \ReflectionMethod($className, $methodName));

        return $attributes[$name] ?? [];
    }

    /**
     * @param class-string    $className
     * @param class-string<T> $name
     *
     * @return list<T>
     */
    public function forClassAndMethod(string $className, string $methodName, string $name): array
    {
        return [
            ...$this->forClass($className, $name),
            ...$this->forMethod($className, $methodName, $name),
        ];
    }

    private function readAttributes(\ReflectionClass|\ReflectionMethod $reflection): array
    {
        $attributeInstances = [];

        foreach ($reflection->getAttributes() as $attribute) {
            if (!str_starts_with($name = $attribute->getName(), 'Symfony\\Bridge\\PhpUnit\\Attribute\\')) {
                continue;
            }

            $attributeInstances[$name][] = $attribute->newInstance();
        }

        return $attributeInstances;
    }
}
