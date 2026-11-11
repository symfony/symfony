<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
trait PhpGeneratorTrait
{
    /**
     * @var array<class-string, string|null>
     */
    private array $valueObjectTransformerIdCache = [];

    /**
     * @param class-string $className
     */
    private function getValueObjectTransformerId(string $className): ?string
    {
        if (\array_key_exists($className, $this->valueObjectTransformerIdCache)) {
            return $this->valueObjectTransformerIdCache[$className];
        }

        if ($this->transformers->has($className)) {
            return $this->valueObjectTransformerIdCache[$className] = $className;
        }

        $reflection = new \ReflectionClass($className);
        if (($parent = $reflection->getParentClass()) && $id = $this->getValueObjectTransformerId($parent->getName())) {
            return $this->valueObjectTransformerIdCache[$className] = $id;
        }

        foreach ($reflection->getInterfaceNames() as $interface) {
            if ($id = $this->getValueObjectTransformerId($interface)) {
                return $this->valueObjectTransformerIdCache[$className] = $id;
            }
        }

        return $this->valueObjectTransformerIdCache[$className] = null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function line(string $line, array $context): string
    {
        return str_repeat('    ', $context['indentation_level']).$line."\n";
    }
}
