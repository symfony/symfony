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

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Resolves interface injectors and inlines them as method calls
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class ResolveInterfaceInjectorsPass implements CompilerPassInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $definition) {
            if (!$definition->getClass()) {
                continue;
            }

            $loaded = false;
            foreach ($container->getInterfaceInjectors() as $injector) {
                if (null !== $definition->getFactoryClass() || null !== $definition->getFactoryService()) {
                    continue;
                }

                if (false === $loaded && null !== $definition->getFile()) {
                    $loaded = true;

                    require_once $definition->getFile();
                }

                if ($injector->supports($definition->getClass())) {
                    $injector->processDefinition($definition);
                }
            }
        }
    }
}
