<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if ($container->has('form.extension')) {
            $container->getDefinition('twig.extension.form')->addTag('twig.extension');
            $reflClass = new \ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension');
            $container->getDefinition('twig.loader.filesystem')->addMethodCall('addPath', array(dirname(dirname($reflClass->getFileName())).'/Resources/views/Form'));
        }

        if ($container->has('fragment.handler')) {
            $container->getDefinition('twig.extension.actions')->addTag('twig.extension');
        }

        if ($container->has('translator')) {
            $container->getDefinition('twig.extension.trans')->addTag('twig.extension');
        }

        if ($container->has('router')) {
            $container->getDefinition('twig.extension.routing')->addTag('twig.extension');
        }

        if ($container->has('fragment.handler')) {
            $container->getDefinition('twig.extension.httpkernel')->addTag('twig.extension');

            // inject Twig in the hinclude service if Twig is the only registered templating engine
            if (
                !$container->hasParameter('templating.engines')
                || array('twig') == $container->getParameter('templating.engines')
            ) {
                $container->getDefinition('fragment.renderer.hinclude')
                    ->addTag('kernel.fragment_renderer', array('alias' => 'hinclude'))
                    ->replaceArgument(0, new Reference('twig'))
                ;
            }
        }

        if ($container->has('request_stack')) {
            $container->getDefinition('twig.extension.httpfoundation')->addTag('twig.extension');
        }

        if ($container->hasParameter('templating.helper.code.file_link_format')) {
            $container->getDefinition('twig.extension.code')->replaceArgument(0, $container->getParameter('templating.helper.code.file_link_format'));
        }

        if ($container->getParameter('kernel.debug') && $container->has('debug.stopwatch')) {
            $container->getDefinition('twig.extension.profiler')->addTag('twig.extension');
        }

        if ($container->has('templating')) {
            $container->getDefinition('twig.cache_warmer')->addTag('kernel.cache_warmer');
        } else {
            $loader = $container->getDefinition('twig.loader.native_filesystem');
            $loader->addTag('twig.loader');
            $loader->setMethodCalls($container->getDefinition('twig.loader.filesystem')->getMethodCalls());

            $container->setDefinition('twig.loader.filesystem', $loader);
        }

        if ($container->has('request')) {
            // we are on Symfony <3.0, where the setContainer method exists
            $container->getDefinition('twig.app_variable')->addMethodCall('setContainer', array(new Reference('service_container')));
        }
    }
}
