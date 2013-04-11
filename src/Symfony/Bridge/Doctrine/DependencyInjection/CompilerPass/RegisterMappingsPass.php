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
     * Hashmap of directory path ot namespace
     * @var array
     */
    protected $mappings;
    /**
     * Mapping file extension, for example '.orm.xml'
     * @var string
     */
    protected $extension;
    /**
     * Parameter name of the entity managers list in the service container.
     * For example 'doctrine.entity_managers'
     * @var string
     */
    protected $managersParameter;
    /**
     * Mapping driver class to use, for example 'Doctrine\ORM\Mapping\Driver\XmlDriver'
     * @var string
     */
    protected $driverClass;
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
     * @param array  $mappings          hashmap of absolute directory paths to namespaces
     * @param string $extension         file extension to look for the mapping files
     * @param string $managersParameter service container parameter name for the managers list
     * @param string $driverClass       name of the mapping driver to instantiate
     * @param string $driverPattern     pattern to get the metadata driver service names
     * @param string $enableParameter   service container parameter that must be
     *      present to enable the mapping. Set to false to not do any check, optional.
     */
    public function __construct(array $mappings, $extension, $managersParameter, $driverClass, $driverPattern, $enableParameter = false)
    {
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
        if (!$this->enabled($container)) {
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
     * @param ContainerBuilder $container passed on in case an extending class
     *      needs access to the container.
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
