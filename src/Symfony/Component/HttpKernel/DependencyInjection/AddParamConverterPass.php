<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds tagged request.param_converter services to converter.manager service.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AddParamConverterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('param_converter.manager')) {
            return;
        }

        $definition = $container->getDefinition('param_converter.manager');
        $disabled = $container->getParameter('param_converter.disabled_converters');
        $container->getParameterBag()->remove('param_converter.disabled_converters');

        foreach ($container->findTaggedServiceIds('request.param_converter') as $id => $converters) {
            foreach ($converters as $converter) {
                $name = isset($converter['converter']) ? $converter['converter'] : null;

                if (null !== $name && \in_array($name, $disabled)) {
                    continue;
                }

                $priority = isset($converter['priority']) ? $converter['priority'] : 0;

                if ('false' === $priority || false === $priority) {
                    $priority = null;
                }

                $definition->addMethodCall('add', [new Reference($id), $priority, $name]);
            }
        }
    }
}
