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
use Symfony\Component\Config\Resource\DirectoryResource;
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

        $twigFilesystemLoaderDefinition = $container->getDefinition('twig.loader.filesystem');

        // register user-configured paths
        foreach ($config['paths'] as $path => $namespace) {
            if (!$namespace) {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($path));
            } else {
                $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($path, $namespace));
            }
            $container->addResource(new DirectoryResource($path));
        }

        // register bundles as Twig namespaces
        foreach ($container->getParameter('kernel.bundles') as $bundle => $class) {
            if (is_dir($dir = $container->getParameter('kernel.root_dir').'/Resources/'.$bundle.'/views')) {
                $this->addTwigPath($twigFilesystemLoaderDefinition, $dir, $bundle);
                $container->addResource(new DirectoryResource($dir));
            }

            $reflection = new \ReflectionClass($class);
            if (is_dir($dir = dirname($reflection->getFilename()).'/Resources/views')) {
                $this->addTwigPath($twigFilesystemLoaderDefinition, $dir, $bundle);
                $container->addResource(new DirectoryResource($dir));
            }
        }

        if (is_dir($dir = $container->getParameter('kernel.root_dir').'/Resources/views')) {
            $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($dir));
            $container->addResource(new DirectoryResource($dir));
        }

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

        if ($container->getParameter('kernel.debug')) {
            $loader->load('debug.xml');

            $container->setDefinition('templating.engine.twig', $container->findDefinition('debug.templating.engine.twig'));
            $container->setAlias('debug.templating.engine.twig', 'templating.engine.twig');
        }

        if (isset($config['autoescape_service']) && isset($config['autoescape_service_method'])) {
            $config['autoescape'] = array(new Reference($config['autoescape_service']), $config['autoescape_service_method']);
        }
        unset($config['autoescape_service'], $config['autoescape_service_method']);

        $container->setParameter('twig.options', $config);

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

    private function addTwigPath($twigFilesystemLoaderDefinition, $dir, $bundle)
    {
        $name = $bundle;
        if ('Bundle' === substr($name, -6)) {
            $name = substr($name, 0, -6);
        }
        $twigFilesystemLoaderDefinition->addMethodCall('addPath', array($dir, $name));
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
