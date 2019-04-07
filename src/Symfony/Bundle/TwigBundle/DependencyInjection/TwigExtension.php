<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\DependencyInjection;

use Symfony\Bundle\TwigBundle\Loader\NativeFilesystemLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Translation\Translator;
use Twig\Extension\ExtensionInterface;
use Twig\Extension\RuntimeExtensionInterface;
use Twig\Loader\LoaderInterface;

/**
 * TwigExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class TwigExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('twig.xml');

        if (class_exists('Symfony\Component\Form\Form')) {
            $loader->load('form.xml');
        }

        if (interface_exists('Symfony\Component\Templating\EngineInterface')) {
            $loader->load('templating.xml');
        }

        if (class_exists(Application::class)) {
            $loader->load('console.xml');
        }

        if (class_exists(Mailer::class)) {
            $loader->load('mailer.xml');
        }

        if (!class_exists(Translator::class)) {
            $container->removeDefinition('twig.translation.extractor');
        }

        foreach ($configs as $key => $config) {
            if (isset($config['globals'])) {
                foreach ($config['globals'] as $name => $value) {
                    if (\is_array($value) && isset($value['key'])) {
                        $configs[$key]['globals'][$name] = [
                            'key' => $name,
                            'value' => $value,
                        ];
                    }
                }
            }
        }

        $configuration = $this->getConfiguration($configs, $container);

        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('twig.exception_listener.controller', $config['exception_controller']);

        $container->setParameter('twig.form.resources', $config['form_themes']);
        $container->setParameter('twig.default_path', $config['default_path']);
        $defaultTwigPath = $container->getParameterBag()->resolveValue($config['default_path']);

        $envConfiguratorDefinition = $container->getDefinition('twig.configurator.environment');
        $envConfiguratorDefinition->replaceArgument(0, $config['date']['format']);
        $envConfiguratorDefinition->replaceArgument(1, $config['date']['interval_format']);
        $envConfiguratorDefinition->replaceArgument(2, $config['date']['timezone']);
        $envConfiguratorDefinition->replaceArgument(3, $config['number_format']['decimals']);
        $envConfiguratorDefinition->replaceArgument(4, $config['number_format']['decimal_point']);
        $envConfiguratorDefinition->replaceArgument(5, $config['number_format']['thousands_separator']);

        $twigFilesystemLoaderDefinition = $container->getDefinition('twig.loader.native_filesystem');

        if ($container->getParameter('kernel.debug')) {
            $twigFilesystemLoaderDefinition->setClass(NativeFilesystemLoader::class);
        }

        // register user-configured paths
        foreach ($config['paths'] as $path => $namespace) {
            if (!$namespace) {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$path]);
            } else {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$path, $namespace]);
            }
        }

        // paths are modified in ExtensionPass if forms are enabled
        $container->getDefinition('twig.cache_warmer')->replaceArgument(2, $config['paths']);
        $container->getDefinition('twig.template_iterator')->replaceArgument(2, $config['paths']);

        foreach ($this->getBundleTemplatePaths($container, $config) as $name => $paths) {
            $namespace = $this->normalizeBundleName($name);
            foreach ($paths as $path) {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$path, $namespace]);
            }

            if ($paths) {
                // the last path must be the bundle views directory
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$path, '!'.$namespace]);
            }
        }

        if (file_exists($dir = $container->getParameter('kernel.root_dir').'/Resources/views')) {
            if ($dir !== $defaultTwigPath) {
                @trigger_error(sprintf('Loading Twig templates from the "%s" directory is deprecated since Symfony 4.2, use "%s" instead.', $dir, $defaultTwigPath), E_USER_DEPRECATED);
            }

            $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$dir]);
        }
        $container->addResource(new FileExistenceResource($dir));

        if (file_exists($defaultTwigPath)) {
            $twigFilesystemLoaderDefinition->addMethodCall('addPath', [$defaultTwigPath]);
        }
        $container->addResource(new FileExistenceResource($defaultTwigPath));

        if (!empty($config['globals'])) {
            $def = $container->getDefinition('twig');
            foreach ($config['globals'] as $key => $global) {
                if (isset($global['type']) && 'service' === $global['type']) {
                    $def->addMethodCall('addGlobal', [$key, new Reference($global['id'])]);
                } else {
                    $def->addMethodCall('addGlobal', [$key, $global['value']]);
                }
            }
        }

        unset(
            $config['form'],
            $config['globals'],
            $config['extensions']
        );

        if (isset($config['autoescape_service']) && isset($config['autoescape_service_method'])) {
            $config['autoescape'] = [new Reference($config['autoescape_service']), $config['autoescape_service_method']];
        }
        unset($config['autoescape_service'], $config['autoescape_service_method']);

        $container->getDefinition('twig')->replaceArgument(1, $config);

        $container->registerForAutoconfiguration(\Twig_ExtensionInterface::class)->addTag('twig.extension');
        $container->registerForAutoconfiguration(\Twig_LoaderInterface::class)->addTag('twig.loader');
        $container->registerForAutoconfiguration(ExtensionInterface::class)->addTag('twig.extension');
        $container->registerForAutoconfiguration(LoaderInterface::class)->addTag('twig.loader');
        $container->registerForAutoconfiguration(RuntimeExtensionInterface::class)->addTag('twig.runtime');

        if (false === $config['cache']) {
            $container->removeDefinition('twig.cache_warmer');
            $container->removeDefinition('twig.template_cache_warmer');
        }
    }

    private function getBundleTemplatePaths(ContainerBuilder $container, array $config)
    {
        $bundleHierarchy = [];
        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            $defaultOverrideBundlePath = $container->getParameterBag()->resolveValue($config['default_path']).'/bundles/'.$name;

            if (file_exists($dir = $container->getParameter('kernel.root_dir').'/Resources/'.$name.'/views')) {
                @trigger_error(sprintf('Loading Twig templates for "%s" from the "%s" directory is deprecated since Symfony 4.2, use "%s" instead.', $name, $dir, $defaultOverrideBundlePath), E_USER_DEPRECATED);

                $bundleHierarchy[$name][] = $dir;
            }
            $container->addResource(new FileExistenceResource($dir));

            if (file_exists($defaultOverrideBundlePath)) {
                $bundleHierarchy[$name][] = $defaultOverrideBundlePath;
            }
            $container->addResource(new FileExistenceResource($defaultOverrideBundlePath));

            if (file_exists($dir = $bundle['path'].'/Resources/views')) {
                $bundleHierarchy[$name][] = $dir;
            }
            $container->addResource(new FileExistenceResource($dir));
        }

        return $bundleHierarchy;
    }

    private function normalizeBundleName($name)
    {
        if ('Bundle' === substr($name, -6)) {
            $name = substr($name, 0, -6);
        }

        return $name;
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/twig';
    }
}
