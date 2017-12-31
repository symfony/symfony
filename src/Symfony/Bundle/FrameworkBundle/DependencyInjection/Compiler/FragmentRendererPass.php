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

@trigger_error('The '.__NAMESPACE__.'\FragmentRendererPass class is deprecated since Symfony 2.7 and will be removed in 3.0. Use Symfony\Component\HttpKernel\DependencyInjection\FragmentRendererPass instead.', E_USER_DEPRECATED);

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Adds services tagged kernel.fragment_renderer as HTTP content rendering strategies.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since version 2.7, to be removed in 3.0. Use Symfony\Component\HttpKernel\DependencyInjection\FragmentRendererPass instead.
 */
class FragmentRendererPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (false === $container->hasDefinition('fragment.handler')) {
            return;
        }

        $definition = $container->getDefinition('fragment.handler');
        foreach (array_keys($container->findTaggedServiceIds('kernel.fragment_renderer')) as $id) {
            // We must assume that the class value has been correctly filled, even if the service is created by a factory
            $class = $container->getDefinition($id)->getClass();

            $interface = 'Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface';
            if (!is_subclass_of($class, $interface)) {
                throw new \InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, $interface));
            }

            $definition->addMethodCall('addRenderer', array(new Reference($id)));
        }
    }
}
