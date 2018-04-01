<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Reference;

/**
 * Registers the expression language providers.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class AddExpressionLanguageProvidersPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        // routing
        if ($container->has('router')) {
            $definition = $container->findDefinition('router');
            foreach ($container->findTaggedServiceIds('routing.expression_language_provider', true) as $id => $attributes) {
                $definition->addMethodCall('addExpressionLanguageProvider', array(new Reference($id)));
            }
        }

        // security
        if ($container->has('security.access.expression_voter')) {
            $definition = $container->findDefinition('security.access.expression_voter');
            foreach ($container->findTaggedServiceIds('security.expression_language_provider', true) as $id => $attributes) {
                $definition->addMethodCall('addExpressionLanguageProvider', array(new Reference($id)));
            }
        }
    }
}
