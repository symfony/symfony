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

use Symfony\Component\DependencyInjection\Alias;
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

        if ($container->getParameter('kernel.debug')) {
            $container->getDefinition('twig.extension.profiler')->addTag('twig.extension');
            $container->getDefinition('twig.extension.debug')->addTag('twig.extension');
        }

        if (!$container->has('templating')) {
            $loader = $container->getDefinition('twig.loader.native_filesystem');
            $loader->addTag('twig.loader');
            $loader->setMethodCalls($container->getDefinition('twig.loader.filesystem')->getMethodCalls());

            $container->setAlias('twig.loader.filesystem', new Alias('twig.loader.native_filesystem', false));
        }

        if ($container->has('assets.packages')) {
            $container->getDefinition('twig.extension.assets')->addTag('twig.extension');
        }
    }
}
