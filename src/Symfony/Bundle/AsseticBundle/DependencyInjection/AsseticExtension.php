<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Semantic asset configuration.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class AsseticExtension extends Extension
{
    /**
     * Loads the configuration.
     *
     * @param array            $configs   An array of configuration settings
     * @param ContainerBuilder $container A ContainerBuilder instance
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $parameterBag = $container->getParameterBag();

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('assetic.xml');
        $loader->load('templating_twig.xml');
        $loader->load('templating_php.xml');

        $config = self::processConfigs($configs, $container->getParameter('kernel.debug'), array_keys($container->getParameter('kernel.bundles')));

        $container->setParameter('assetic.debug', $config['debug']);
        $container->setParameter('assetic.use_controller', $config['use_controller']);
        $container->setParameter('assetic.read_from', $config['read_from']);
        $container->setParameter('assetic.write_to', $config['write_to']);

        $container->setParameter('assetic.java.bin', $config['java']);
        $container->setParameter('assetic.node.bin', $config['node']);
        $container->setParameter('assetic.sass.bin', $config['sass']);

        // register filters
        foreach ($config['filters'] as $name => $filter) {
            if (isset($filter['resource'])) {
                $loader->load($parameterBag->resolveValue($filter['resource']));
                unset($filter['resource']);
            } else {
                $loader->load('filters/'.$name.'.xml');
            }

            if (isset($filter['file'])) {
                $container->getDefinition('assetic.filter.'.$name)->setFile($filter['file']);
                unset($filter['file']);
            }

            foreach ($filter as $key => $value) {
                $container->setParameter('assetic.filter.'.$name.'.'.$key, $value);
            }
        }

        // twig functions
        $container->getDefinition('assetic.twig_extension')->replaceArgument(2, $config['twig']['functions']);

        // choose dynamic or static
        if ($parameterBag->resolveValue($parameterBag->get('assetic.use_controller'))) {
            $loader->load('controller.xml');
            $container->getDefinition('assetic.helper.dynamic')->addTag('templating.helper', array('alias' => 'assetic'));
            $container->removeDefinition('assetic.helper.static');
        } else {
            $loader->load('asset_writer.xml');
            $container->getDefinition('assetic.helper.static')->addTag('templating.helper', array('alias' => 'assetic'));
            $container->removeDefinition('assetic.helper.dynamic');
        }

        // register config resources
        self::registerFormulaResources($container, $parameterBag->resolveValue($config['bundles']));
    }

    /**
     * Merges the user's config arrays.
     *
     * @param array   $configs An array of config arrays
     * @param Boolean $debug   The debug mode
     * @param array   $bundles An array of all bundle names
     *
     * @return array The merged config
     */
    static protected function processConfigs(array $configs, $debug, array $bundles)
    {
        $processor = new Processor();
        $configuration = new Configuration($debug, $bundles);
        return $processor->processConfiguration($configuration, $configs);
    }

    /**
     * Registers factory resources for certain bundles.
     *
     * @param ContainerBuilder $container The container
     * @param array            $bundles   An array of select bundle names
     *
     * @throws InvalidArgumentException If registering resources from a bundle that doesn't exist
     */
    static protected function registerFormulaResources(ContainerBuilder $container, array $bundles)
    {
        $map = $container->getParameter('kernel.bundles');
        $am  = $container->getDefinition('assetic.asset_manager');

        // bundle views/ directories and kernel overrides
        foreach ($bundles as $name) {
            $rc = new \ReflectionClass($map[$name]);
            foreach (array('twig', 'php') as $engine) {
                $container->setDefinition(
                    'assetic.'.$engine.'_directory_resource.'.$name,
                    self::createDirectoryResourceDefinition($name, $engine, array(
                        $container->getParameter('kernel.root_dir').'/Resources/'.$name.'/views',
                        dirname($rc->getFileName()).'/Resources/views',
                    ))
                );
            }
        }

        // kernel views/ directory
        foreach (array('twig', 'php') as $engine) {
            $container->setDefinition(
                'assetic.'.$engine.'_directory_resource.kernel',
                self::createDirectoryResourceDefinition('', $engine, array(
                    $container->getParameter('kernel.root_dir').'/Resources/views',
                ))
            );
        }
    }

    /**
     * Creates a directory resource definition.
     *
     * If more than one directory is provided a coalescing definition will be
     * returned.
     *
     * @param string $bundle A bundle name or empty string
     * @param string $engine The templating engine
     * @param array  $dirs   An array of directories to merge
     *
     * @return Definition A resource definition
     */
    static protected function createDirectoryResourceDefinition($bundle, $engine, array $dirs)
    {
        $dirResources = array();
        foreach ($dirs as $dir) {
            $dirResources[] = $dirResource = new Definition('%assetic.directory_resource.class%');
            $dirResource
                ->addArgument(new Reference('templating.loader'))
                ->addArgument($bundle)
                ->addArgument($dir)
                ->addArgument('/^[^.]+\.[^.]+\.'.$engine.'$/')
                ->setPublic(false);
        }

        if (1 == count($dirResources)) {
            // no need to coalesce
            $definition = $dirResources[0];
        } else {
            $definition = new Definition('%assetic.coalescing_directory_resource.class%');
            $definition
                ->addArgument($dirResources)
                ->setPublic(false);
        }

        return $definition
            ->addTag('assetic.templating.'.$engine)
            ->addTag('assetic.formula_resource', array('loader' => $engine));
    }

    /**
     * Returns the base path for the XSD files.
     *
     * @return string The XSD base path
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__ . '/../Resources/config/schema';
    }

    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/assetic';
    }
}
