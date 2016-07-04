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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

/**
 * Base class for the doctrine bundles to provide a compiler pass class that
 * helps to register doctrine mappings.
 *
 * The compiler pass is meant to register the mappings with the metadata
 * chain driver corresponding to one of the object managers.
 *
 * For concrete implementations that are easy to use, see the
 * RegisterXyMappingsPass classes in the DoctrineBundle resp.
 * DoctrineMongodbBundle, DoctrineCouchdbBundle and DoctrinePhpcrBundle.
 *
 * @author David Buchmann <david@liip.ch>
 */
abstract class RegisterMappingsPass implements CompilerPassInterface
{
    /**
     * DI object for the driver to use, either a service definition for a
     * private service or a reference for a public service.
     *
     * @var Definition|Reference
     */
    protected $driver;

    /**
     * List of namespaces handled by the driver.
     *
     * @var string[]
     */
    protected $namespaces;

    /**
     * List of potential container parameters that hold the object manager name
     * to register the mappings with the correct metadata driver, for example
     * array('acme.manager', 'doctrine.default_entity_manager').
     *
     * @var string[]
     */
    protected $managerParameters;

    /**
     * Naming pattern of the metadata chain driver service ids, for example
     * 'doctrine.orm.%s_metadata_driver'.
     *
     * @var string
     */
    protected $driverPattern;

    /**
     * A name for a parameter in the container. If set, this compiler pass will
     * only do anything if the parameter is present. (But regardless of the
     * value of that parameter.
     *
     * @var string
     */
    protected $enabledParameter;

    /**
     * Naming pattern for the configuration service id, for example
     * 'doctrine.orm.%s_configuration'.
     *
     * @var string
     */
    private $configurationPattern;

    /**
     * Method name to call on the configuration service. This depends on the
     * Doctrine implementation. For example addEntityNamespace.
     *
     * @var string
     */
    private $registerAliasMethodName;

    /**
     * Map of alias to namespace.
     *
     * @var string[]
     */
    private $aliasMap;

    /**
     * Constructor.
     *
     * The $managerParameters is an ordered list of container parameters that could provide the
     * name of the manager to register these namespaces and alias on. The first non-empty name
     * is used, the others skipped.
     *
     * The $aliasMap parameter can be used to define bundle namespace shortcuts like the
     * DoctrineBundle provides automatically for objects in the default Entity/Document folder.
     *
     * @param Definition|Reference $driver                  Driver DI definition or reference
     * @param string[]             $namespaces              List of namespaces handled by $driver
     * @param string[]             $managerParameters       List of container parameters that could
     *                                                      hold the manager name.
     * @param string               $driverPattern           Pattern for the metadata driver service name
     * @param string               $enabledParameter        Service container parameter that must be
     *                                                      present to enable the mapping. Set to false
     *                                                      to not do any check, optional.
     * @param string               $configurationPattern    Pattern for the Configuration service name
     * @param string               $registerAliasMethodName Name of Configuration class method to
     *                                                      register alias.
     * @param string[]             $aliasMap                Map of alias to namespace
     *
     * @since Support for bundle alias was added in Symfony 2.6
     */
    public function __construct($driver, array $namespaces, array $managerParameters, $driverPattern, $enabledParameter = false, $configurationPattern = '', $registerAliasMethodName = '', array $aliasMap = array())
    {
        $this->driver = $driver;
        $this->namespaces = $namespaces;
        $this->managerParameters = $managerParameters;
        $this->driverPattern = $driverPattern;
        $this->enabledParameter = $enabledParameter;
        if (count($aliasMap) && (!$configurationPattern || !$registerAliasMethodName)) {
            throw new \InvalidArgumentException('configurationPattern and registerAliasMethodName are required to register namespace alias');
        }
        $this->configurationPattern = $configurationPattern;
        $this->registerAliasMethodName = $registerAliasMethodName;
        $this->aliasMap = $aliasMap;
    }

    /**
     * Register mappings and alias with the metadata drivers.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$this->enabled($container)) {
            return;
        }

        $mappingDriverDef = $this->getDriver($container);
        $chainDriverDefService = $this->getChainDriverServiceName($container);
        // Definition for a Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain
        $chainDriverDef = $container->getDefinition($chainDriverDefService);
        foreach ($this->namespaces as $namespace) {
            $chainDriverDef->addMethodCall('addDriver', array($mappingDriverDef, $namespace));
        }

        if (!count($this->aliasMap)) {
            return;
        }

        $configurationServiceName = $this->getConfigurationServiceName($container);
        // Definition of the Doctrine\...\Configuration class specific to the Doctrine flavour.
        $configurationServiceDefinition = $container->getDefinition($configurationServiceName);
        foreach ($this->aliasMap as $alias => $namespace) {
            $configurationServiceDefinition->addMethodCall($this->registerAliasMethodName, array($alias, $namespace));
        }
    }

    /**
     * Get the service name of the metadata chain driver that the mappings
     * should be registered with.
     *
     * @param ContainerBuilder $container
     *
     * @return string The name of the chain driver service
     *
     * @throws InvalidArgumentException if non of the managerParameters has a
     *                                  non-empty value.
     */
    protected function getChainDriverServiceName(ContainerBuilder $container)
    {
        return sprintf($this->driverPattern, $this->getManagerName($container));
    }

    /**
     * Create the service definition for the metadata driver.
     *
     * @param ContainerBuilder $container passed on in case an extending class
     *                                    needs access to the container.
     *
     * @return Definition|Reference the metadata driver to add to all chain drivers
     */
    protected function getDriver(ContainerBuilder $container)
    {
        return $this->driver;
    }

    /**
     * Get the service name from the pattern and the configured manager name.
     *
     * @param ContainerBuilder $container
     *
     * @return string a service definition name
     *
     * @throws InvalidArgumentException if none of the managerParameters has a
     *                                  non-empty value.
     */
    private function getConfigurationServiceName(ContainerBuilder $container)
    {
        return sprintf($this->configurationPattern, $this->getManagerName($container));
    }

    /**
     * Determine the manager name.
     *
     * The default implementation loops over the managerParameters and returns
     * the first non-empty parameter.
     *
     * @param ContainerBuilder $container
     *
     * @return string The name of the active manager
     *
     * @throws InvalidArgumentException If none of the managerParameters is found in the container.
     */
    private function getManagerName(ContainerBuilder $container)
    {
        foreach ($this->managerParameters as $param) {
            if ($container->hasParameter($param)) {
                $name = $container->getParameter($param);
                if ($name) {
                    return $name;
                }
            }
        }

        throw new \InvalidArgumentException(sprintf(
            'Could not find the manager name parameter in the container. Tried the following parameter names: "%s"',
            implode('", "', $this->managerParameters)
        ));
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
     * @return bool whether this compiler pass really should register the mappings
     */
    protected function enabled(ContainerBuilder $container)
    {
        return !$this->enabledParameter || $container->hasParameter($this->enabledParameter);
    }
}
