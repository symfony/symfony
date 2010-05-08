<?php

namespace Symfony\Components\DependencyInjection;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Builder is a DI container that provides an interface to build the services.
 *
 * @package    Symfony
 * @subpackage Components_DependencyInjection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Builder extends Container implements AnnotatedContainerInterface
{
    protected $definitions = array();
    protected $aliases     = array();
    protected $loading     = array();

    /**
     * Sets a service.
     *
     * @param string $id      The service identifier
     * @param object $service The service instance
     */
    public function setService($id, $service)
    {
        unset($this->definitions[$id]);
        unset($this->aliases[$id]);

        parent::setService($id, $service);
    }

    /**
     * Returns true if the given service is defined.
     *
     * @param  string  $id      The service identifier
     *
     * @return Boolean true if the service is defined, false otherwise
     */
    public function hasService($id)
    {
        return isset($this->definitions[$id]) || isset($this->aliases[$id]) || parent::hasService($id);
    }

    /**
     * Gets a service.
     *
     * @param  string $id              The service identifier
     * @param  int    $invalidBehavior The behavior when the service does not exist
     *
     * @return object The associated service
     *
     * @throws \InvalidArgumentException if the service is not defined
     * @throws \LogicException if the service has a circular reference to itself
     *
     * @see Reference
     */
    public function getService($id, $invalidBehavior = Container::EXCEPTION_ON_INVALID_REFERENCE)
    {
        try {
            return parent::getService($id, Container::EXCEPTION_ON_INVALID_REFERENCE);
        } catch (\InvalidArgumentException $e) {
            if (isset($this->loading[$id])) {
                throw new \LogicException(sprintf('The service "%s" has a circular reference to itself.', $id));
            }

            if (!$this->hasDefinition($id) && isset($this->aliases[$id])) {
                return $this->getService($this->aliases[$id]);
            }

            try {
                $definition = $this->getDefinition($id);
            } catch (\InvalidArgumentException $e) {
                if (Container::EXCEPTION_ON_INVALID_REFERENCE !== $invalidBehavior) {
                    return null;
                }

                throw $e;
            }

            $this->loading[$id] = true;

            $service = $this->createService($definition, $id);

            unset($this->loading[$id]);

            return $service;
        }
    }

    /**
     * Merges a BuilderConfiguration with the current Builder configuration.
     *
     * Service definitions overrides the current defined ones.
     *
     * But for parameters, they are overridden by the current ones. It allows
     * the parameters passed to the container constructor to have precedence
     * over the loaded ones.
     *
     * $container = new Builder(array('foo' => 'bar'));
     * $loader = new LoaderXXX($container);
     * $loader->load('resource_name');
     * $container->register('foo', new stdClass());
     *
     * In the above example, even if the loaded resource defines a foo
     * parameter, the value will still be 'bar' as defined in the builder
     * constructor.
     */
    public function merge(BuilderConfiguration $configuration = null)
    {
        if (null === $configuration) {
            return;
        }

        $this->addDefinitions($configuration->getDefinitions());
        $this->addAliases($configuration->getAliases());

        $currentParameters = $this->getParameters();
        foreach ($configuration->getParameters() as $key => $value) {
            $this->setParameter($key, $value);
        }
        $this->addParameters($currentParameters);

        foreach ($this->parameters as $key => $value) {
            $this->parameters[$key] = self::resolveValue($value, $this->parameters);
        }
    }

    /**
     * Gets all service ids.
     *
     * @return array An array of all defined service ids
     */
    public function getServiceIds()
    {
        return array_unique(array_merge(array_keys($this->getDefinitions()), array_keys($this->aliases), parent::getServiceIds()));
    }

    /**
     * Adds the service aliases.
     *
     * @param array $aliases An array of aliases
     */
    public function addAliases(array $aliases)
    {
        foreach ($aliases as $alias => $id) {
            $this->setAlias($alias, $id);
        }
    }

    /**
     * Sets the service aliases.
     *
     * @param array $definitions An array of service definitions
     */
    public function setAliases(array $aliases)
    {
        $this->aliases = array();
        $this->addAliases($aliases);
    }

    /**
     * Sets an alias for an existing service.
     *
     * @param string $alias The alias to create
     * @param string $id    The service to alias
     */
    public function setAlias($alias, $id)
    {
        unset($this->definitions[$alias]);

        $this->aliases[$alias] = $id;
    }

    /**
     * Returns true if an alias exists under the given identifier.
     *
     * @param  string  $id The service identifier
     *
     * @return Boolean true if the alias exists, false otherwise
     */
    public function hasAlias($id)
    {
        return array_key_exists($id, $this->aliases);
    }

    /**
     * Gets all defined aliases.
     *
     * @return array An array of aliases
     */
    public function getAliases()
    {
        return $this->aliases;
    }

    /**
     * Gets an alias.
     *
     * @param  string  $id The service identifier
     *
     * @return string The aliased service identifier
     *
     * @throws \InvalidArgumentException if the alias does not exist
     */
    public function getAlias($id)
    {
        if (!$this->hasAlias($id)) {
            throw new \InvalidArgumentException(sprintf('The service alias "%s" does not exist.', $id));
        }

        return $this->aliases[$id];
    }

    /**
     * Registers a service definition.
     *
     * This methods allows for simple registration of service definition
     * with a fluid interface.
     *
     * @param  string $id    The service identifier
     * @param  string $class The service class
     *
     * @return Definition A Definition instance
     */
    public function register($id, $class)
    {
        return $this->setDefinition($id, new Definition($class));
    }

    /**
     * Adds the service definitions.
     *
     * @param Definition[] $definitions An array of service definitions
     */
    public function addDefinitions(array $definitions)
    {
        foreach ($definitions as $id => $definition) {
            $this->setDefinition($id, $definition);
        }
    }

    /**
     * Sets the service definitions.
     *
     * @param array $definitions An array of service definitions
     */
    public function setDefinitions(array $definitions)
    {
        $this->definitions = array();
        $this->addDefinitions($definitions);
    }

    /**
     * Gets all service definitions.
     *
     * @return array An array of Definition instances
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * Sets a service definition.
     *
     * @param  string     $id         The service identifier
     * @param  Definition $definition A Definition instance
     */
    public function setDefinition($id, Definition $definition)
    {
        unset($this->aliases[$id]);

        return $this->definitions[$id] = $definition;
    }

    /**
     * Returns true if a service definition exists under the given identifier.
     *
     * @param  string  $id The service identifier
     *
     * @return Boolean true if the service definition exists, false otherwise
     */
    public function hasDefinition($id)
    {
        return array_key_exists($id, $this->definitions);
    }

    /**
     * Gets a service definition.
     *
     * @param  string  $id The service identifier
     *
     * @return Definition A Definition instance
     *
     * @throws \InvalidArgumentException if the service definition does not exist
     */
    public function getDefinition($id)
    {
        if (!$this->hasDefinition($id)) {
            throw new \InvalidArgumentException(sprintf('The service definition "%s" does not exist.', $id));
        }

        return $this->definitions[$id];
    }

    /**
     * Creates a service for a service definition.
     *
     * @param  Definition $definition A service definition instance
     * @param  string     $id         The service identifier
     *
     * @return object              The service described by the service definition
     *
     * @throws \InvalidArgumentException When configure callable is not callable
     */
    protected function createService(Definition $definition, $id)
    {
        if (null !== $definition->getFile()) {
            require_once self::resolveValue($definition->getFile(), $this->parameters);
        }

        $r = new \ReflectionClass(self::resolveValue($definition->getClass(), $this->parameters));

        $arguments = $this->resolveServices(self::resolveValue($definition->getArguments(), $this->parameters));

        if (null !== $definition->getConstructor()) {
            $service = call_user_func_array(array(self::resolveValue($definition->getClass(), $this->parameters), $definition->getConstructor()), $arguments);
        } else {
            $service = null === $r->getConstructor() ? $r->newInstance() : $r->newInstanceArgs($arguments);
        }

        if ($definition->isShared()) {
            $this->services[$id] = $service;
        }

        foreach ($definition->getMethodCalls() as $call) {
            $services = self::getServiceConditionals($call[1]);

            $ok = true;
            foreach ($services as $s) {
                if (!$this->hasService($s)) {
                    $ok = false;
                    break;
                }
            }

            if ($ok) {
                call_user_func_array(array($service, $call[0]), $this->resolveServices(self::resolveValue($call[1], $this->parameters)));
            }
        }

        if ($callable = $definition->getConfigurator()) {
            if (is_array($callable) && is_object($callable[0]) && $callable[0] instanceof Reference) {
                $callable[0] = $this->getService((string) $callable[0]);
            } elseif (is_array($callable)) {
                $callable[0] = self::resolveValue($callable[0], $this->parameters);
            }

            if (!is_callable($callable)) {
                throw new \InvalidArgumentException(sprintf('The configure callable for class "%s" is not a callable.', get_class($service)));
            }

            call_user_func($callable, $service);
        }

        return $service;
    }

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @param  mixed $value A value
     *
     * @return mixed The same value with all placeholders replaced by their values
     *
     * @throws \RuntimeException if a placeholder references a parameter that does not exist
     */
    static public function resolveValue($value, $parameters)
    {
        if (is_array($value)) {
            $args = array();
            foreach ($value as $k => $v) {
                $args[self::resolveValue($k, $parameters)] = self::resolveValue($v, $parameters);
            }

            $value = $args;
        } else if (is_string($value)) {
            if (preg_match('/^%([^%]+)%$/', $value, $match)) {
                // we do this to deal with non string values (boolean, integer, ...)
                // the preg_replace_callback converts them to strings
                if (!array_key_exists($name = strtolower($match[1]), $parameters)) {
                    throw new \RuntimeException(sprintf('The parameter "%s" must be defined.', $name));
                }

                $value = $parameters[$name];
            } else {
                $replaceParameter = function ($match) use ($parameters, $value)
                {
                    if (!array_key_exists($name = strtolower($match[2]), $parameters)) {
                        throw new \RuntimeException(sprintf('The parameter "%s" must be defined (used in the following expression: "%s").', $name, $value));
                    }

                    return $parameters[$name];
                };

                $value = str_replace('%%', '%', preg_replace_callback('/(?<!%)(%)([^%]+)\1/', $replaceParameter, $value));
            }
        }

        return $value;
    }

    /**
     * Replaces service references by the real service instance.
     *
     * @param  mixed $value A value
     *
     * @return mixed The same value with all service references replaced by the real service instances
     */
    public function resolveServices($value)
    {
        if (is_array($value)) {
            foreach ($value as &$v) {
                $v = $this->resolveServices($v);
            }
        } else if (is_object($value) && $value instanceof Reference) {
            $value = $this->getService((string) $value, $value->getInvalidBehavior());
        }

        return $value;
    }

    /**
     * Returns service ids for a given annotation.
     *
     * @param string $name The annotation name
     *
     * @return array An array of annotations
     */
    public function findAnnotatedServiceIds($name)
    {
        $annotations = array();
        foreach ($this->getDefinitions() as $id => $definition) {
            if ($definition->getAnnotation($name)) {
                $annotations[$id] = $definition->getAnnotation($name);
            }
        }

        return $annotations;
    }

    static public function getServiceConditionals($value)
    {
        $services = array();

        if (is_array($value)) {
            foreach ($value as $v) {
                $services = array_unique(array_merge($services, self::getServiceConditionals($v)));
            }
        } elseif (is_object($value) && $value instanceof Reference && $value->getInvalidBehavior() === Container::IGNORE_ON_INVALID_REFERENCE) {
            $services[] = (string) $value;
        }

        return $services;
    }
}
