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

use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Yaml\Parser as YamlParser;

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
        if (!interface_exists('Symfony\Component\Translation\TranslatorInterface')) {
            $container->removeDefinition('twig.extension.trans');
        }

        if (!class_exists('Symfony\Component\Yaml\Yaml')) {
            $container->removeDefinition('twig.extension.yaml');
        }

        if ($container->has('form.extension')) {
            $container->getDefinition('twig.extension.form')->addTag('twig.extension');
            $reflClass = new \ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension');
            $container->getDefinition('twig.loader.native_filesystem')->addMethodCall('addPath', array(dirname(dirname($reflClass->getFileName())).'/Resources/views/Form'));
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

        if ($container->getParameter('kernel.debug')) {
            $container->getDefinition('twig.extension.profiler')->addTag('twig.extension');
            $container->getDefinition('twig.extension.debug')->addTag('twig.extension');
        }

        $composerRootDir = $this->getComposerRootDir($container->getParameter('kernel.root_dir'));
        $twigLoader = $container->getDefinition('twig.loader.native_filesystem');
        if ($container->has('templating')) {
            $loader = $container->getDefinition('twig.loader.filesystem');
            $loader->setMethodCalls($twigLoader->getMethodCalls());
            $loader->replaceArgument(2, $composerRootDir);

            $twigLoader->clearTag('twig.loader');
        } else {
            $twigLoader->replaceArgument(1, $composerRootDir);
            $container->setAlias('twig.loader.filesystem', new Alias('twig.loader.native_filesystem', false));
        }

        if ($container->has('assets.packages')) {
            $container->getDefinition('twig.extension.assets')->addTag('twig.extension');
        }

        $container->addResource(new ClassExistenceResource(YamlParser::class));
        if (class_exists(YamlParser::class)) {
            $container->getDefinition('twig.extension.yaml')->addTag('twig.extension');
        }

        $container->addResource(new ClassExistenceResource(Stopwatch::class));
        if (class_exists(Stopwatch::class)) {
            $container->getDefinition('twig.extension.debug.stopwatch')->addTag('twig.extension');
        }

        $container->addResource(new ClassExistenceResource(ExpressionLanguage::class));
        if (class_exists(ExpressionLanguage::class)) {
            $container->getDefinition('twig.extension.expression')->addTag('twig.extension');
        }
    }

    private function getComposerRootDir($rootDir)
    {
        $dir = $rootDir;
        while (!file_exists($dir.'/composer.json')) {
            if ($dir === dirname($dir)) {
                return $rootDir;
            }

            $dir = dirname($dir);
        }

        return $dir;
    }
}
