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
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        // routing
        if ($container->has('router.default')) {
            $definition = $container->findDefinition('router.default');
            foreach ($container->findTaggedServiceIds('routing.expression_language_provider', true) as $id => $attributes) {
                $definition->addMethodCall('addExpressionLanguageProvider', [new Reference($id)]);
            }
        }
    }
}
