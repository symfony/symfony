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
use Symfony\Component\Workflow\Workflow;

/**
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class ExtensionPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!class_exists('Symfony\Component\Asset\Packages')) {
            $container->removeDefinition('twig.extension.assets');
        }

        if (!class_exists('Symfony\Component\ExpressionLanguage\Expression')) {
            $container->removeDefinition('twig.extension.expression');
        }

        if (!interface_exists('Symfony\Component\Routing\Generator\UrlGeneratorInterface')) {
            $container->removeDefinition('twig.extension.routing');
        }

        if (!class_exists('Symfony\Component\Yaml\Yaml')) {
            $container->removeDefinition('twig.extension.yaml');
        }

        $viewDir = \dirname((new \ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension'))->getFileName(), 2).'/Resources/views';
        $templateIterator = $container->getDefinition('twig.template_iterator');
        $templatePaths = $templateIterator->getArgument(1);
        $cacheWarmer = null;
        if ($container->hasDefinition('twig.cache_warmer')) {
            $cacheWarmer = $container->getDefinition('twig.cache_warmer');
            $cacheWarmerPaths = $cacheWarmer->getArgument(2);
        }
        $loader = $container->getDefinition('twig.loader.native_filesystem');

        if ($container->has('mailer')) {
            $emailPath = $viewDir.'/Email';
            $loader->addMethodCall('addPath', [$emailPath, 'email']);
            $loader->addMethodCall('addPath', [$emailPath, '!email']);
            $templatePaths[$emailPath] = 'email';
            if ($cacheWarmer) {
                $cacheWarmerPaths[$emailPath] = 'email';
            }
        }

        if ($container->has('form.extension')) {
            $container->getDefinition('twig.extension.form')->addTag('twig.extension');

            $coreThemePath = $viewDir.'/Form';
            $loader->addMethodCall('addPath', [$coreThemePath]);
            $templatePaths[$coreThemePath] = null;
            if ($cacheWarmer) {
                $cacheWarmerPaths[$coreThemePath] = null;
            }
        }

        $templateIterator->replaceArgument(1, $templatePaths);
        if ($cacheWarmer) {
            $container->getDefinition('twig.cache_warmer')->replaceArgument(2, $cacheWarmerPaths);
        }

        if ($container->has('router')) {
            $container->getDefinition('twig.extension.routing')->addTag('twig.extension');
        }

        if ($container->has('fragment.handler')) {
            $container->getDefinition('twig.extension.httpkernel')->addTag('twig.extension');
            $container->getDefinition('twig.runtime.httpkernel')->addTag('twig.runtime');

            if ($container->hasDefinition('fragment.renderer.hinclude')) {
                $container->getDefinition('fragment.renderer.hinclude')
                    ->addTag('kernel.fragment_renderer', ['alias' => 'hinclude'])
                ;
            }
        }

        if (!$container->has('http_kernel')) {
            $container->removeDefinition('twig.controller.preview_error');
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

        if ($container->has('web_link.add_link_header_listener')) {
            $container->getDefinition('twig.extension.weblink')->addTag('twig.extension');
        }

        $container->setAlias('twig.loader.filesystem', new Alias('twig.loader.native_filesystem', false));

        if ($container->has('assets.packages')) {
            $container->getDefinition('twig.extension.assets')->addTag('twig.extension');
        }

        if ($container->hasDefinition('twig.extension.yaml')) {
            $container->getDefinition('twig.extension.yaml')->addTag('twig.extension');
        }

        if (class_exists('Symfony\Component\Stopwatch\Stopwatch')) {
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
