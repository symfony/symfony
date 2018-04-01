<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\DependencyInjection\Compiler;

use Symphony\Component\DependencyInjection\Alias;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\Workflow\Workflow;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!class_exists('Symphony\Component\Asset\Packages')) {
            $container->removeDefinition('twig.extension.assets');
        }

        if (!class_exists('Symphony\Component\ExpressionLanguage\Expression')) {
            $container->removeDefinition('twig.extension.expression');
        }

        if (!interface_exists('Symphony\Component\Routing\Generator\UrlGeneratorInterface')) {
            $container->removeDefinition('twig.extension.routing');
        }

        if (!class_exists('Symphony\Component\Yaml\Yaml')) {
            $container->removeDefinition('twig.extension.yaml');
        }

        if ($container->has('form.extension')) {
            $container->getDefinition('twig.extension.form')->addTag('twig.extension');
            $reflClass = new \ReflectionClass('Symphony\Bridge\Twig\Extension\FormExtension');

            $coreThemePath = dirname(dirname($reflClass->getFileName())).'/Resources/views/Form';
            $container->getDefinition('twig.loader.native_filesystem')->addMethodCall('addPath', array($coreThemePath));

            $paths = $container->getDefinition('twig.cache_warmer')->getArgument(2);
            $paths[$coreThemePath] = null;
            $container->getDefinition('twig.cache_warmer')->replaceArgument(2, $paths);
            $container->getDefinition('twig.template_iterator')->replaceArgument(2, $paths);
        }

        if ($container->has('router')) {
            $container->getDefinition('twig.extension.routing')->addTag('twig.extension');
        }

        if ($container->has('fragment.handler')) {
            $container->getDefinition('twig.extension.httpkernel')->addTag('twig.extension');

            // inject Twig in the hinclude service if Twig is the only registered templating engine
            if ((!$container->hasParameter('templating.engines') || array('twig') == $container->getParameter('templating.engines')) && $container->hasDefinition('fragment.renderer.hinclude')) {
                $container->getDefinition('fragment.renderer.hinclude')
                    ->addTag('kernel.fragment_renderer', array('alias' => 'hinclude'))
                    ->replaceArgument(0, new Reference('twig'))
                ;
            }
        }

        if ($container->has('request_stack')) {
            $container->getDefinition('twig.extension.httpfoundation')->addTag('twig.extension');
        }

        if ($container->getParameter('kernel.debug')) {
            $container->getDefinition('twig.extension.profiler')->addTag('twig.extension');

            // only register if the improved version from DebugBundle is *not* present
            if (!$container->has('twig.extension.dump')) {
                $container->getDefinition('twig.extension.debug')->addTag('twig.extension');
            }
        }

        $twigLoader = $container->getDefinition('twig.loader.native_filesystem');
        if ($container->has('templating')) {
            $loader = $container->getDefinition('twig.loader.filesystem');
            $loader->setMethodCalls(array_merge($twigLoader->getMethodCalls(), $loader->getMethodCalls()));

            $twigLoader->clearTag('twig.loader');
        } else {
            $container->setAlias('twig.loader.filesystem', new Alias('twig.loader.native_filesystem', false));
            $container->removeDefinition('templating.engine.twig');
        }

        if ($container->has('assets.packages')) {
            $container->getDefinition('twig.extension.assets')->addTag('twig.extension');
        }

        if ($container->hasDefinition('twig.extension.yaml')) {
            $container->getDefinition('twig.extension.yaml')->addTag('twig.extension');
        }

        if (class_exists('Symphony\Component\Stopwatch\Stopwatch')) {
            $container->getDefinition('twig.extension.debug.stopwatch')->addTag('twig.extension');
        }

        if ($container->hasDefinition('twig.extension.expression')) {
            $container->getDefinition('twig.extension.expression')->addTag('twig.extension');
        }

        if (!class_exists(Workflow::class) || !$container->has('workflow.registry')) {
            $container->removeDefinition('workflow.twig_extension');
        } else {
            $container->getDefinition('workflow.twig_extension')->addTag('twig.extension');
        }
    }
}
