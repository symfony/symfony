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
        $path = explode('\\', $fullClassName);
        $className = array_pop($path);
        [$namespace, $uses] = $this->extractFromFullClassName($fullClassName);

        foreach (class_uses($fullClassName) as $traitFullClassName) {
            [, $traitUses] = $this->extractFromFullClassName($traitFullClassName);
            $uses = array_merge($uses, $traitUses);
        }

        return new NameScope($className, $namespace, $uses);
    }

    private function extractFromFullClassName(string $fullClassName): array
    {
        $reflection = new \ReflectionClass($fullClassName);
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
