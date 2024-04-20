<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler;

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
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('security.expression_language')) {
            $definition = $container->findDefinition('security.expression_language');
            foreach ($container->findTaggedServiceIds('security.expression_language_provider', true) as $id => $attributes) {
                $definition->addMethodCall('registerProvider', [new Reference($id)]);
            }
        }

        if (!$container->hasDefinition('cache.system')) {
            $container->removeDefinition('cache.security_expression_language');
            $container->removeDefinition('cache.security_is_granted_attribute_expression_language');
        }
    }
}
