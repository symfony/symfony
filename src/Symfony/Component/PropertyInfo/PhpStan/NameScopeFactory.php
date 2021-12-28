<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\PhpStan;

use phpDocumentor\Reflection\Types\ContextFactory;

/**
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 *
 * @internal
 */
final class NameScopeFactory
{
    public function create(string $fullClassName): NameScope
    {
        $reflection = new \ReflectionClass($fullClassName);
        $path = explode('\\', $fullClassName);
        $className = array_pop($path);
        [$namespace, $uses] = $this->extractFromFullClassName($reflection);

        $uses = array_merge($uses, $this->collectUses($reflection));

        return new NameScope($className, $namespace, $uses);
    }

    private function collectUses(\ReflectionClass $reflection): array
    {
        $uses = [$this->extractFromFullClassName($reflection)[1]];

        foreach ($reflection->getTraits() as $traitReflection) {
            $uses[] = $this->extractFromFullClassName($traitReflection)[1];
        }

        if (false !== $parentClass = $reflection->getParentClass()) {
            $uses[] = $this->collectUses($parentClass);
        }

        return $uses ? array_merge(...$uses) : [];
    }

    private function extractFromFullClassName(\ReflectionClass $reflection): array
    {
        $namespace = trim($reflection->getNamespaceName(), '\\');
        $fileName = $reflection->getFileName();

        if (\is_string($fileName) && is_file($fileName)) {
            if (false === $contents = file_get_contents($fileName)) {
                throw new \RuntimeException(sprintf('Unable to read file "%s".', $fileName));
            }

            $factory = new ContextFactory();
            $context = $factory->createForNamespace($namespace, $contents);

            return [$namespace, $context->getNamespaceAliases()];
        }

        return [$namespace, []];
    }
}
