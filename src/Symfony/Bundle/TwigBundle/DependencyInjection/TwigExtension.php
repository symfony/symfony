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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * TwigExtension.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jeremy Mikola <jmikola@gmail.com>
 */
class TwigExtension extends Extension
{
    /**
     * Responds to the twig configuration parameter.
     *
     * @param array            $configs
     * @param ContainerBuilder $container
     */
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

        if (!interface_exists('Symfony\Component\Translation\TranslatorInterface')) {
            $container->removeDefinition('twig.translation.extractor');
        }

        foreach ($configs as $key => $config) {
            if (isset($config['globals'])) {
                foreach ($config['globals'] as $name => $value) {
                    if (is_array($value) && isset($value['key'])) {
                        $configs[$key]['globals'][$name] = array(
                            'key' => $name,
                            'value' => $value,
                        );
                    }
                }
            }
        }

        $configuration = $this->getConfiguration($configs, $container);

        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('twig.exception_listener.controller', $config['exception_controller']);

        $container->setParameter('twig.form.resources', $config['form_themes']);

        $envConfiguratorDefinition = $container->getDefinition('twig.configurator.environment');
        $envConfiguratorDefinition->replaceArgument(0, $config['date']['format']);
        $envConfiguratorDefinition->replaceArgument(1, $config['date']['interval_format']);
        $envConfiguratorDefinition->replaceArgument(2, $config['date']['timezone']);
        $envConfiguratorDefinition->replaceArgument(3, $config['number_format']['decimals']);
        $envConfiguratorDefinition->replaceArgument(4, $config['number_format']['decimal_point']);
        $envConfiguratorDefinition->replaceArgument(5, $config['number_format']['thousands_separator']);

        $twigFilesystemLoaderDefinition = $container->getDefinition('twig.loader.native_filesystem');

        // register user-configured paths
        foreach ($config['paths'] as $path => $namespace) {
            if (!$namespace) {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($path));
            } else {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($path, $namespace));
            }
        }

        $container->getDefinition('twig.cache_warmer')->replaceArgument(2, $config['paths']);
        $container->getDefinition('twig.template_iterator')->replaceArgument(2, $config['paths']);

        $bundleHierarchy = $this->getBundleHierarchy($container);

        foreach ($bundleHierarchy as $name => $bundle) {
            $namespace = $this->normalizeBundleName($name);

            foreach ($bundle['children'] as $child) {
                foreach ($bundleHierarchy[$child]['paths'] as $path) {
                    $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($path, $namespace));
                }
            }

            foreach ($bundle['paths'] as $path) {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($path, $namespace));
            }
        }

        if (is_dir($dir = $container->getParameter('kernel.root_dir').'/Resources/views')) {
            $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($dir));
        }
        $container->addResource(new FileExistenceResource($dir));

        if (!empty($config['globals'])) {
            $def = $container->getDefinition('twig');
            foreach ($config['globals'] as $key => $global) {
                if (isset($global['type']) && 'service' === $global['type']) {
                    $def->addMethodCall('addGlobal', array($key, new Reference($global['id'])));
                } else {
                    $def->addMethodCall('addGlobal', array($key, $global['value']));
                }
            }
        }

        unset(
            $config['form'],
            $config['globals'],
            $config['extensions']
        );

        if (isset($config['autoescape_service']) && isset($config['autoescape_service_method'])) {
            $config['autoescape'] = array(new Reference($config['autoescape_service']), $config['autoescape_service_method']);
        }
        unset($config['autoescape_service'], $config['autoescape_service_method']);

        $container->getDefinition('twig')->replaceArgument(1, $config);

        $this->addClassesToCompile(array(
            'Twig_Environment',
            'Twig_Extension',
            'Twig_Extension_Core',
            'Twig_Extension_Escaper',
            'Twig_Extension_Optimizer',
            'Twig_LoaderInterface',
            'Twig_Markup',
            'Twig_Template',
        ));
    }

    private function getBundleHierarchy(ContainerBuilder $container)
    {
        $bundleHierarchy = array();

        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            if (!array_key_exists($name, $bundleHierarchy)) {
                $bundleHierarchy[$name] = array(
                    'paths' => array(),
                    'parents' => array(),
                    'children' => array(),
                );
            }

            if (is_dir($dir = $container->getParameter('kernel.root_dir').'/Resources/'.$name.'/views')) {
                $bundleHierarchy[$name]['paths'][] = $dir;
            }
            $container->addResource(new FileExistenceResource($dir));

            if (is_dir($dir = $bundle['path'].'/Resources/views')) {
                $bundleHierarchy[$name]['paths'][] = $dir;
            }
            $container->addResource(new FileExistenceResource($dir));

            if (null === $bundle['parent']) {
                continue;
            }

            $bundleHierarchy[$name]['parents'][] = $bundle['parent'];

            if (!array_key_exists($bundle['parent'], $bundleHierarchy)) {
                $bundleHierarchy[$bundle['parent']] = array(
                    'paths' => array(),
                    'parents' => array(),
                    'children' => array(),
                );
            }

            $bundleHierarchy[$bundle['parent']]['children'] = array_merge($bundleHierarchy[$name]['children'], array($name), $bundleHierarchy[$bundle['parent']]['children']);

            foreach ($bundleHierarchy[$bundle['parent']]['parents'] as $parent) {
                $bundleHierarchy[$name]['parents'][] = $parent;
                $bundleHierarchy[$parent]['children'] = array_merge($bundleHierarchy[$name]['children'], array($name), $bundleHierarchy[$parent]['children']);
            }

            foreach ($bundleHierarchy[$name]['children'] as $child) {
                $bundleHierarchy[$child]['parents'] = array_merge($bundleHierarchy[$child]['parents'], $bundleHierarchy[$name]['parents']);
            }
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
