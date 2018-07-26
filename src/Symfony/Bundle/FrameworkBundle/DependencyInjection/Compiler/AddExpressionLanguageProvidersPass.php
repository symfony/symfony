<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the expression language providers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
            foreach ($container->findTaggedServiceIds('routing.expression_language_provider') as $id => $attributes) {
                $definition->addMethodCall('addExpressionLanguageProvider', array(new Reference($id)));
            }
        }

        // security
        if ($container->has('security.access.expression_voter')) {
            $definition = $container->findDefinition('security.access.expression_voter');
            foreach ($container->findTaggedServiceIds('security.expression_language_provider') as $id => $attributes) {
                $definition->addMethodCall('addExpressionLanguageProvider', array(new Reference($id)));
            }
        }
    }
}
