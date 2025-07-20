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

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Attribute\AsServiceLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AsServiceLocatorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $commandServices = $container->findTaggedServiceIds('container.service_locator', true);

        foreach ($commandServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());

            if (!$r = $container->getReflectionClass($class)) {
                throw new \InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            }

            if ($attribute = ($r->getAttributes(AsServiceLocator::class)[0] ?? null)) {
                $attribute = $attribute->newInstance();
                $definition->setArgument(0, new TaggedIteratorArgument($attribute->tag, $attribute->indexAttribute, $attribute->defaultIndexMethod, false, $attribute->defaultPriorityMethod, $attribute->exclude, $attribute->excludeSelf));
            }
        }
    }
}
