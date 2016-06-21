<?php

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\AmbiguousDefinition;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Martin Hasoň <martin.hason@gmail.com>
 */
class RegisterClassNamedServicesPass implements CompilerPassInterface
{
    private $container;
    private $types;
    private $definedTypes;
    private $reflectionClasses = array();

    public function process(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->types = array();
        $this->definedTypes = array();

        //$this->populateAvailableTypes('service_container', new Definition(Container::class));

        foreach ($container->getDefinitions() as $id => $definition) {
            $this->populateAvailableTypes($id, $definition);
        }

        foreach ($this->types as $type => $services) {
            $this->registerService($type, $services);
        }

        $this->container = null;
        $this->types = array();
        $this->definedTypes = array();
        $this->reflectionClasses = array();
    }

    private function populateAvailableTypes($id, Definition $definition)
    {
        if ($definition->isAbstract()) {
            return;
        }

        if (!$reflectionClass = $this->getReflectionClass($id, $definition)) {
            return;
        }

        foreach ($definition->getAutowiringTypes() as $type) {
            $this->definedTypes[$type] = true;
            $this->set($type, $id);
        }

        foreach ($reflectionClass->getInterfaces() as $interfaceClass) {
            $this->set($interfaceClass->name, $id);
        }

        do {
            $this->set($reflectionClass->name, $id);
        } while ($reflectionClass = $reflectionClass->getParentClass());
    }

    private function getReflectionClass($id, Definition $definition)
    {
        if (isset($this->reflectionClasses[$id])) {
            return $this->reflectionClasses[$id];
        }

        if (!$class = $definition->getClass()) {
            return;
        }

        $class = $this->container->getParameterBag()->resolveValue($class);

        try {
            return $this->reflectionClasses[$id] = new \ReflectionClass($class);
        } catch (\ReflectionException $reflectionException) {
            return;
        }
    }

    private function set($type, $id)
    {
        if (isset($this->definedTypes[$type])) {
            return;
        }

        $this->types[$type][] = $id;
    }

    private function registerService($type, array $services)
    {
        if (1 === count($services)) {
            $service = reset($services);
            //$public = 'service_container' === $service ?: $this->container->getDefinition($service)->isPublic();
            $public = $this->container->getDefinition($service)->isPublic();
            $this->container->setAlias($type, new Alias($service, $public));
        } else {
            $this->container->setDefinition($type, new AmbiguousDefinition($type, $services));
        }
    }
}

