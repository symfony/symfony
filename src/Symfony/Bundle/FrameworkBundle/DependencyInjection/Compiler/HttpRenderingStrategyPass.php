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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Adds services tagged kernel.content_renderer_strategy as HTTP content rendering strategies.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpRenderingStrategyPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('http_content_renderer')) {
            return;
        }

        $definition = $container->getDefinition('http_content_renderer');
        foreach (array_keys($container->findTaggedServiceIds('kernel.content_renderer_strategy')) as $id) {
            $definition->addMethodCall('addStrategy', array(new Reference($id)));
        }
    }
}
