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

/**
 * Base class for the doctrine bundles to provide a compiler pass class that
 * helps to register doctrine mappings. For concrete implementations that are
 * easy to use, see the RegisterXyMappingsPass classes in the DoctrineBundle
 * resp. DoctrineMongodbBundle, DoctrineCouchdbBundle and DoctrinePhpcrBundle.
 *
 * @author David Buchmann <david@liip.ch>
 */
abstract class RegisterMappingsPass implements CompilerPassInterface
{
    /**
     * DI object for the driver to use, either a service definition for a
     * private service or a reference for a public service.
     * @var Definition|Reference
     */
    protected $driver;

    /**
     * List of namespaces handled by the driver
     * @var string[]
     */
    protected $namespaces;

    /**
     * Parameter name of the entity managers list in the service container.
     * For example 'doctrine.entity_managers'
     * @var string
     */
    protected $managersParameter;

    /**
     * Naming pattern of the metadata service ids, for example 'doctrine.orm.%s_metadata_driver'
     * @var string
     */
    protected $driverPattern;

    /**
     * A name for a parameter in the container. If set, this compiler pass will
     * only do anything if the parameter is present. (But regardless of the
     * value of that parameter.
     * @var string
     */
    protected $enabledParameter;

    /**
     * @param Definition|Reference $driver            driver DI definition or reference
     * @param string[]             $namespaces        list of namespaces handled by $driver
     * @param string               $managersParameter service container parameter name for the managers list
     * @param string               $driverPattern     pattern to get the metadata driver service names
     * @param string               $enableParameter   service container parameter that must be
     *      present to enable the mapping. Set to false to not do any check, optional.
     */
    public function __construct($driver, array $namespaces, $managersParameter, $driverPattern, $enableParameter = false)
    {
        $this->driver = $driver;
        $this->namespaces = $namespaces;
        $this->managersParameter = $managersParameter;
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
        if (!$this->enabled($container)) {
            return;
        }

        $mappingDriverDef = $this->getDriver($container);
        foreach ($container->getParameter($this->managersParameter) as $name => $manager) {
            $chainDriverDefService = sprintf($this->driverPattern, $name);
            $chainDriverDef = $container->getDefinition($chainDriverDefService);
            foreach ($this->namespaces as $namespace) {
                $chainDriverDef->addMethodCall('addDriver', array($mappingDriverDef, $namespace));
            }
        }
    }

    /**
     * Create the service definition for the metadata driver.
     *
     * @param ContainerBuilder $container passed on in case an extending class
     *      needs access to the container.
     *
     * @return Definition|Reference the metadata driver to add to all chain drivers
     */
    protected function getDriver(ContainerBuilder $container)
    {
        return $this->driver;
    }

    /**
     * Determine whether this mapping should be activated or not. This allows
     * to take this decision with the container builder available.
     *
     * This default implementation checks if the class has the enabledParameter
     * configured and if so if that parameter is present in the container.
     *
     * @param ContainerBuilder $container
     *
     * @return boolean whether this compiler pass really should register the mappings
     */
    protected function enabled(ContainerBuilder $container)
    {
        return !$this->enabledParameter || $container->hasParameter($this->enabledParameter);
    }
}
