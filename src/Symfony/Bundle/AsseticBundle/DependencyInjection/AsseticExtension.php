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

            foreach ($filter as $key => $value) {
                $container->setParameter('assetic.filter.'.$name.'.'.$key, $value);
            }
        }

        // choose dynamic or static
        if ($parameterBag->resolveValue($parameterBag->get('assetic.use_controller'))) {
            $loader->load('controller.xml');
            $container->setParameter('assetic.twig_extension.class', '%assetic.twig_extension.dynamic.class%');
        } else {
            $loader->load('asset_writer.xml');
            $container->setParameter('assetic.twig_extension.class', '%assetic.twig_extension.static.class%');
        }

        // register config resources
        self::registerFormulaResources($container, $parameterBag->resolveValue($config['bundles']));
    }

    static protected function processConfigs(array $configs, $debug, array $bundles)
    {
        $configuration = new Configuration();
        $tree = $configuration->getConfigTree($debug, $bundles);

        $processor = new Processor();
        return $processor->process($tree, $configs);
    }

    static protected function registerFormulaResources(ContainerBuilder $container, array $bundles)
    {
        $map = $container->getParameter('kernel.bundles');

        if ($diff = array_diff($bundles, array_keys($map))) {
            throw new \InvalidArgumentException(sprintf('The following bundles are not registered: "%s"', implode('", "', $diff)));
        }

        $am = $container->getDefinition('assetic.asset_manager');

        // bundle views/ directories
        foreach ($bundles as $name) {
            $rc = new \ReflectionClass($map[$name]);
            if (is_dir($dir = dirname($rc->getFileName()).'/Resources/views')) {
                // FIXME: must also look for templates in %kernel.root_dir%/Resources/%bundle%/views
                foreach (array('twig', 'php') as $engine) {
                    $container->setDefinition(
                        'assetic.'.$engine.'_directory_resource.'.$name,
                        self::createDirectoryResourceDefinition($name, $dir, $engine)
                    );
                }
            }
        }

        // kernel views/ directory
        if (is_dir($dir = $container->getParameter('kernel.root_dir').'/Resources/views')) {
            foreach (array('twig', 'php') as $engine) {
                $container->setDefinition(
                    'assetic.'.$engine.'_directory_resource.kernel',
                    self::createDirectoryResourceDefinition('', $dir, $engine)
                );
            }
        }
    }

    /**
     * @todo decorate an abstract xml definition
     */
    static protected function createDirectoryResourceDefinition($bundle, $dir, $engine)
    {
        $definition = new Definition('%assetic.directory_resource.class%');

        $definition
            ->addArgument(new Reference('templating.loader'))
            ->addArgument($bundle)
            ->addArgument($dir)
            ->addArgument('/^[^.]+\.[^.]+\.'.$engine.'$/')
            ->addTag('assetic.templating.'.$engine)
            ->addTag('assetic.formula_resource', array('loader' => $engine))
            ->setPublic(false)
        ;

        return $definition;
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
