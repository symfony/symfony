<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\DependencyInjection\CompilerPass;

use Symfony\Component\HttpKernel\Kernel;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

use FOS\UserBundle\DependencyInjection\OrmMappingBundleInterface;

/**
 * Base class for the doctrine bundles to provide a compiler pass class that
 * helps to register doctrine mappings.
 *
 * @author David Buchmann <david@liip.ch>
 */
abstract class RegisterMappingsPass implements CompilerPassInterface
{
    protected $mappings;
    protected $extension = '.orm.xml';
    protected $managersParameter = 'doctrine.entity_managers';
    protected $driverClass = 'Doctrine\ORM\Mapping\Driver\XmlDriver';
    protected $driverPattern = 'doctrine.orm.%s_metadata_driver';
    protected $enabledParameter;

    /**
     * @param array  $mappings          hashmap of absolute directory paths to namespaces
     * @param string $extension         file extension to look for the mapping files
     * @param string $managersParameter service container parameter name for the managers list
     * @param string $driverClass       name of the mapping driver to instantiate
     * @param string $driverPattern     pattern to get the metadata driver service names
     * @param string $enableParameter   service container parameter that must
     *      be present to enable the mapping. Set to false to not do any check.
     */
    public function __construct(
        array $mappings,
        $extension,
        $managersParameter,
        $driverClass,
        $driverPattern,
        $enableParameter
    ) {
        $this->mappings = $mappings;
        $this->extension = $extension;
        $this->managersParameter = $managersParameter;
        $this->driverClass = $driverClass;
        $this->driverPattern = $driverPattern;
        $this->enabledParameter = $enableParameter;
    }

    /**
     * Register mappings with the metadata drivers.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (! $this->enabled($container)) {
            return;
        }

        $mappingDriverDef = $this->buildDriver($container);
        foreach ($container->getParameter($this->managersParameter) as $name => $manager) {
            $chainDriverDefService = sprintf($this->driverPattern, $name);
            $chainDriverDef = $container->getDefinition($chainDriverDefService);
            foreach ($this->mappings as $namespace) {
                $chainDriverDef->addMethodCall('addDriver', array($mappingDriverDef, $namespace));
            }
        }
    }

    /**
     * Create the service definition for the metadata driver.
     *
     * @param ContainerBuilder $container
     *
     * @return Definition the metadata driver to add to all chain drivers
     */
    protected function buildDriver(ContainerBuilder $container)
    {
        $arguments = array($this->mappings, $this->extension);
        $locator = new Definition('Doctrine\Common\Persistence\Mapping\Driver\SymfonyFileLocator', $arguments);
        return new Definition($this->driverClass, array($locator));
    }

    /**
     * Determine whether this mapping should be activated or not.
     *
     * Checks if the container contains the parameter with the name
     * enabledParameter.
     *
     * @param ContainerBuilder $container
     *
     * @return boolean whether this pass should register the mappings
     */
    protected function enabled(ContainerBuilder $container)
    {
        return ! $this->enabledParameter || $container->hasParameter($this->enabledParameter);
    }
}
