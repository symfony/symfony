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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class AttributeAutoconfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (80000 > \PHP_VERSION_ID) {
            return;
        }

        $autoconfiguredAttributes = $container->getAutoconfiguredAttributes();

        foreach ($container->getDefinitions() as $id => $definition) {
            if (!$definition->isAutoconfigured()
                || $definition->isAbstract()
                || $definition->hasTag('container.ignore_attributes')
                || !($reflector = $container->getReflectionClass($definition->getClass(), false))
            ) {
                continue;
            }

            $instanceof = $definition->getInstanceofConditionals();
            $conditionals = $instanceof[$reflector->getName()] ?? new ChildDefinition('');
            foreach ($reflector->getAttributes() as $attribute) {
                if ($configurator = $autoconfiguredAttributes[$attribute->getName()] ?? null) {
                    $configurator($conditionals, $attribute->newInstance(), $reflector);
                }
            }
            $instanceof[$reflector->getName()] = $conditionals;
            $definition->setInstanceofConditionals($instanceof);
        }
    }
}
