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
    public function create(string $calledClassName, ?string $declaringClassName = null): NameScope
    {
        $declaringClassName ??= $calledClassName;

        $path = explode('\\', $calledClassName);
        $calledClassName = array_pop($path);

        $declaringReflection = new \ReflectionClass($declaringClassName);
        [$declaringNamespace, $declaringUses] = $this->extractFromFullClassName($declaringReflection);
        $declaringUses = array_merge($declaringUses, $this->collectUses($declaringReflection));

        return new NameScope($calledClassName, $declaringNamespace, $declaringUses);
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
