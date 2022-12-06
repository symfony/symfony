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

use Symfony\Component\DependencyInjection\Attribute\AsAliasOf;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Reads #[AsAliasOf] attributes on definitions that are autowired
 * and don't have the "container.ignore_attributes" tag.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class AutowireAsAliasOfPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($this->accept($definition) && $reflectionClass = $container->getReflectionClass($definition->getClass(), false)) {
                $this->processClass($container, $reflectionClass);
            }
        }
    }

    private function accept(Definition $definition): bool
    {
        return !$definition->hasTag('container.ignore_attributes') && $definition->isAutowired();
    }

    private function processClass(ContainerBuilder $container, \ReflectionClass $class): void
    {
        if (!$attribute = ($class->getAttributes(AsAliasOf::class)[0] ?? null)?->newInstance()) {
            return;
        }

        $container->setAlias($class->name, $attribute->id);
    }
}
