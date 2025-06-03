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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Base class for the doctrine bundles to provide a compiler pass class that
 * helps to register doctrine mappings.
 *
 * The compiler pass is meant to register the mappings with the metadata
 * chain driver corresponding to one of the object managers.
 *
 * For concrete implementations, see the RegisterXyMappingsPass classes
 * in the DoctrineBundle resp.
 * DoctrineMongodbBundle, DoctrineCouchdbBundle and DoctrinePhpcrBundle.
 *
 * @author David Buchmann <david@liip.ch>
 */
abstract class RegisterMappingsPass implements CompilerPassInterface
{
    /**
     * The $managerParameters is an ordered list of container parameters that could provide the
     * name of the manager to register these namespaces and alias on. The first non-empty name
     * is used, the others skipped.
     *
     * The $aliasMap parameter can be used to define bundle namespace shortcuts like the
     * DoctrineBundle provides automatically for objects in the default Entity/Document folder.
     *
     * @param Definition|Reference $driver                  Driver DI definition or reference
     * @param string[]             $namespaces              List of namespaces handled by $driver
     * @param string[]             $managerParameters       list of container parameters that could
     *                                                      hold the manager name
     * @param string               $driverPattern           Pattern for the metadata chain driver service ids (e.g. "doctrine.orm.%s_metadata_driver")
     * @param string|false         $enabledParameter        Service container parameter that must be
     *                                                      present to enable the mapping (regardless of the
     *                                                      parameter value). Pass false to not do any check.
     * @param string               $configurationPattern    Pattern for the Configuration service name,
     *                                                      for example 'doctrine.orm.%s_configuration'.
     * @param string               $registerAliasMethodName Method name to call on the configuration service. This
     *                                                      depends on the Doctrine implementation.
     *                                                      For example addEntityNamespace.
     * @param string[]             $aliasMap                Map of alias to namespace
     */
    public function __construct(
        protected Definition|Reference $driver,
        protected array $namespaces,
        protected array $managerParameters,
        protected string $driverPattern,
        protected string|false $enabledParameter = false,
        private readonly string $configurationPattern = '',
        private readonly string $registerAliasMethodName = '',
        private readonly array $aliasMap = [],
    ) {
        if ($aliasMap && (!$configurationPattern || !$registerAliasMethodName)) {
            throw new \InvalidArgumentException('configurationPattern and registerAliasMethodName are required to register namespace alias.');
        }
    }

    /**
     * Register mappings and alias with the metadata drivers.
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$this->enabled($container)) {
            return;
        }

        $mappingDriverDef = $this->getDriver($container);
        $chainDriverDefService = $this->getChainDriverServiceName($container);
        // Definition for a Doctrine\Persistence\Mapping\Driver\MappingDriverChain
        $chainDriverDef = $container->getDefinition($chainDriverDefService);
        foreach ($this->namespaces as $namespace) {
            $chainDriverDef->addMethodCall('addDriver', [$mappingDriverDef, $namespace]);
        }

        if (!\count($this->aliasMap)) {
            return;
        }

        $configurationServiceName = $this->getConfigurationServiceName($container);
        // Definition of the Doctrine\...\Configuration class specific to the Doctrine flavour.
        $configurationServiceDefinition = $container->getDefinition($configurationServiceName);
        foreach ($this->aliasMap as $alias => $namespace) {
            $configurationServiceDefinition->addMethodCall($this->registerAliasMethodName, [$alias, $namespace]);
        }
    }

    /**
     * Get the service name of the metadata chain driver that the mappings
     * should be registered with.
     *
     * @throws InvalidArgumentException if non of the managerParameters has a
     *                                  non-empty value
     */
    protected function getChainDriverServiceName(ContainerBuilder $container): string
    {
        return \sprintf($this->driverPattern, $this->getManagerName($container));
    }

    /**
     * Create the service definition for the metadata driver.
     *
     * @param ContainerBuilder $container Passed on in case an extending class
     *                                    needs access to the container
     */
    protected function getDriver(ContainerBuilder $container): Definition|Reference
    {
        return $this->driver;
    }

    /**
     * Get the service name from the pattern and the configured manager name.
     *
     * @throws InvalidArgumentException if none of the managerParameters has a
     *                                  non-empty value
     */
    private function getConfigurationServiceName(ContainerBuilder $container): string
    {
        return \sprintf($this->configurationPattern, $this->getManagerName($container));
    }

    /**
     * Determine the manager name.
     *
     * The default implementation loops over the managerParameters and returns
     * the first non-empty parameter.
     *
     * @throws InvalidArgumentException if none of the managerParameters is found in the container
     */
    private function getManagerName(ContainerBuilder $container): string
    {
        foreach ($this->managerParameters as $param) {
            if ($container->hasParameter($param)) {
                $name = $container->getParameter($param);
                if ($name) {
                    return $name;
                }
            }
        }

        throw new InvalidArgumentException(\sprintf('Could not find the manager name parameter in the container. Tried the following parameter names: "%s".', implode('", "', $this->managerParameters)));
    }

    /**
     * Determine whether this mapping should be activated or not. This allows
     * to take this decision with the container builder available.
     *
     * This default implementation checks if the class has the enabledParameter
     * configured and if so if that parameter is present in the container.
     */
    protected function enabled(ContainerBuilder $container): bool
    {
        return !$this->enabledParameter || $container->hasParameter($this->enabledParameter);
    }
}
