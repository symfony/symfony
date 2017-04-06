<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;

/**
 * Definition represents a service definition.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Definition
{
    private $class;
    private $file;
    private $factory;
    private $shared = true;
    private $deprecated = false;
    private $deprecationTemplate = 'The "%service_id%" service is deprecated. You should stop using it, as it will soon be removed.';
    private $properties = array();
    private $calls = array();
    private $instanceof = array();
    private $configurator;
    private $tags = array();
    private $public = true;
    private $synthetic = false;
    private $abstract = false;
    private $lazy = false;
    private $decoratedService;
    private $autowired = false;
    private $autowiringTypes = array();
    private $changes = array();
    private $trackChanges = true;

    protected $arguments;

    /**
     * @param string|null $class     The service class
     * @param array       $arguments An array of arguments to pass to the service constructor
     */
    public function __construct($class = null, array $arguments = array())
    {
        if (null !== $class) {
            $this->setClass($class);
        }
        $this->arguments = $arguments;
    }

    /**
     * Sets a factory.
     *
     * @param string|array $factory A PHP function or an array containing a class/Reference and a method to call
     *
     * @return $this
     */
    public function setFactory($factory)
    {
        if (is_string($factory) && strpos($factory, '::') !== false) {
            $factory = explode('::', $factory, 2);
        }

        $this->trackChange('factory');
        $this->factory = $factory;

        return $this;
    }

    /**
     * Gets the factory.
     *
     * @return string|array The PHP function or an array containing a class/Reference and a method to call
     */
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Sets the service that this service is decorating.
     *
     * @param null|string $id        The decorated service id, use null to remove decoration
     * @param null|string $renamedId The new decorated service id
     * @param int         $priority  The priority of decoration
     *
     * @return $this
     *
     * @throws InvalidArgumentException In case the decorated service id and the new decorated service id are equals.
     */
    public function setDecoratedService($id, $renamedId = null, $priority = 0)
    {
        if ($renamedId && $id == $renamedId) {
            throw new InvalidArgumentException(sprintf('The decorated service inner name for "%s" must be different than the service name itself.', $id));
        }

        $this->trackChange('decorated_service');

        if (null === $id) {
            $this->decoratedService = null;
        } else {
            $this->decoratedService = array($id, $renamedId, (int) $priority);
        }

        return $this;
    }

    /**
     * Gets the service that this service is decorating.
     *
     * @return null|array An array composed of the decorated service id, the new id for it and the priority of decoration, null if no service is decorated
     */
    public function getDecoratedService()
    {
        return $this->decoratedService;
    }

    /**
     * Sets the service class.
     *
     * @param string $class The service class
     *
     * @return $this
     */
    public function setClass($class)
    {
        $this->trackChange('class');
        $this->class = $class;

        return $this;
    }

    /**
     * Gets the service class.
     *
     * @return string|null The service class
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Sets the arguments to pass to the service constructor/factory method.
     *
     * @param array $arguments An array of arguments
     *
     * @return $this
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    public function setProperties(array $properties)
    {
        $this->properties = $properties;

        return $this;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function setProperty($name, $value)
    {
        $this->properties[$name] = $value;

        return $this;
    }

    /**
     * Adds an argument to pass to the service constructor/factory method.
     *
     * @param mixed $argument An argument
     *
     * @return $this
     */
    public function addArgument($argument)
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * Sets a specific argument.
     *
     * @param int|string $index
     * @param mixed      $argument
     *
     * @return $this
     *
     * @throws OutOfBoundsException When the replaced argument does not exist
     */
    public function replaceArgument($index, $argument)
    {
        if (0 === count($this->arguments)) {
            throw new OutOfBoundsException('Cannot replace arguments if none have been configured yet.');
        }

        if (is_int($index) && ($index < 0 || $index > count($this->arguments) - 1)) {
            throw new OutOfBoundsException(sprintf('The index "%d" is not in the range [0, %d].', $index, count($this->arguments) - 1));
        }

        if (!array_key_exists($index, $this->arguments)) {
            throw new OutOfBoundsException(sprintf('The argument "%s" doesn\'t exist.', $index));
        }

        $this->arguments[$index] = $argument;

        return $this;
    }

    /**
     * Gets the arguments to pass to the service constructor/factory method.
     *
     * @return array The array of arguments
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Gets an argument to pass to the service constructor/factory method.
     *
     * @param int|string $index
     *
     * @return mixed The argument value
     *
     * @throws OutOfBoundsException When the argument does not exist
     */
    public function getArgument($index)
    {
        if (!array_key_exists($index, $this->arguments)) {
            throw new OutOfBoundsException(sprintf('The argument "%s" doesn\'t exist.', $index));
        }

        return $this->arguments[$index];
    }

    /**
     * Sets the methods to call after service initialization.
     *
     * @param array $calls An array of method calls
     *
     * @return $this
     */
    public function setMethodCalls(array $calls = array())
    {
        $this->calls = array();
        foreach ($calls as $call) {
            $this->addMethodCall($call[0], $call[1]);
        }

        return $this;
    }

    /**
     * Adds a method to call after service initialization.
     *
     * @param string $method    The method name to call
     * @param array  $arguments An array of arguments to pass to the method call
     *
     * @return $this
     *
     * @throws InvalidArgumentException on empty $method param
     */
    public function addMethodCall($method, array $arguments = array())
    {
        if (empty($method)) {
            throw new InvalidArgumentException('Method name cannot be empty.');
        }
        $this->calls[] = array($method, $arguments);

        return $this;
    }

    /**
     * Removes a method to call after service initialization.
     *
     * @param string $method The method name to remove
     *
     * @return $this
     */
    public function removeMethodCall($method)
    {
        foreach ($this->calls as $i => $call) {
            if ($call[0] === $method) {
                unset($this->calls[$i]);
                break;
            }
        }

        return $this;
    }

    /**
     * Check if the current definition has a given method to call after service initialization.
     *
     * @param string $method The method name to search for
     *
     * @return bool
     */
    public function hasMethodCall($method)
    {
        foreach ($this->calls as $call) {
            if ($call[0] === $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the methods to call after service initialization.
     *
     * @return array An array of method calls
     */
    public function getMethodCalls()
    {
        return $this->calls;
    }

    /**
     * Sets the definition templates to conditionally apply on the current definition, keyed by parent interface/class.
     *
     * @param $instanceof Definition[]
     */
    public function setInstanceofConditionals(array $instanceof)
    {
        $this->instanceof = $instanceof;

        return $this;
    }

    /**
     * Gets the definition templates to conditionally apply on the current definition, keyed by parent interface/class.
     *
     * @return Definition[]
     */
    public function getInstanceofConditionals()
    {
        return $this->instanceof;
    }

    /**
     * Sets tags for this definition.
     *
     * @param array $tags
     *
     * @return $this
     */
    public function setTags(array $tags)
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Returns all tags.
     *
     * @return array An array of tags
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Gets a tag by name.
     *
     * @param string $name The tag name
     *
     * @return array An array of attributes
     */
    public function getTag($name)
    {
        return isset($this->tags[$name]) ? $this->tags[$name] : array();
    }

    /**
     * Adds a tag for this definition.
     *
     * @param string $name       The tag name
     * @param array  $attributes An array of attributes
     *
     * @return $this
     */
    public function addTag($name, array $attributes = array())
    {
        $this->tags[$name][] = $attributes;

        return $this;
    }

    /**
     * Whether this definition has a tag with the given name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasTag($name)
    {
        return isset($this->tags[$name]);
    }

    /**
     * Clears all tags for a given name.
     *
     * @param string $name The tag name
     *
     * @return $this
     */
    public function clearTag($name)
    {
        unset($this->tags[$name]);

        return $this;
    }

    /**
     * Clears the tags for this definition.
     *
     * @return $this
     */
    public function clearTags()
    {
        $this->tags = array();

        return $this;
    }

    /**
     * Sets a file to require before creating the service.
     *
     * @param string $file A full pathname to include
     *
     * @return $this
     */
    public function setFile($file)
    {
        $this->trackChange('file');
        $this->file = $file;

        return $this;
    }

    /**
     * Gets the file to require before creating the service.
     *
     * @return string|null The full pathname to include
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Sets if the service must be shared or not.
     *
     * @param bool $shared Whether the service must be shared or not
     *
     * @return $this
     */
    public function setShared($shared)
    {
        $this->trackChange('shared');
        $this->shared = (bool) $shared;

        return $this;
    }

    /**
     * Whether this service is shared.
     *
     * @return bool
     */
    public function isShared()
    {
        return $this->shared;
    }

    /**
     * Sets the visibility of this service.
     *
     * @param bool $boolean
     *
     * @return $this
     */
    public function setPublic($boolean)
    {
        $this->trackChange('public');
        $this->public = (bool) $boolean;

        return $this;
    }

    /**
     * Whether this service is public facing.
     *
     * @return bool
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * Sets the lazy flag of this service.
     *
     * @param bool $lazy
     *
     * @return $this
     */
    public function setLazy($lazy)
    {
        $this->trackChange('lazy');
        $this->lazy = (bool) $lazy;

        return $this;
    }

    /**
     * Whether this service is lazy.
     *
     * @return bool
     */
    public function isLazy()
    {
        return $this->lazy;
    }

    /**
     * Sets whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @param bool $boolean
     *
     * @return $this
     */
    public function setSynthetic($boolean)
    {
        $this->trackChange('synthetic');
        $this->synthetic = (bool) $boolean;

        return $this;
    }

    /**
     * Whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @return bool
     */
    public function isSynthetic()
    {
        return $this->synthetic;
    }

    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @param bool $boolean
     *
     * @return $this
     */
    public function setAbstract($boolean)
    {
        $this->trackChange('abstract');
        $this->abstract = (bool) $boolean;

        return $this;
    }

    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @return bool
     */
    public function isAbstract()
    {
        return $this->abstract;
    }

    /**
     * Whether this definition is deprecated, that means it should not be called
     * anymore.
     *
     * @param bool   $status
     * @param string $template Template message to use if the definition is deprecated
     *
     * @return $this
     *
     * @throws InvalidArgumentException When the message template is invalid.
     */
    public function setDeprecated($status = true, $template = null)
    {
        if (null !== $template) {
            if (preg_match('#[\r\n]|\*/#', $template)) {
                throw new InvalidArgumentException('Invalid characters found in deprecation template.');
            }

            if (false === strpos($template, '%service_id%')) {
                throw new InvalidArgumentException('The deprecation template must contain the "%service_id%" placeholder.');
            }

            $this->deprecationTemplate = $template;
        }

        $this->trackChange('deprecated');
        $this->deprecated = (bool) $status;

        return $this;
    }

    /**
     * Whether this definition is deprecated, that means it should not be called
     * anymore.
     *
     * @return bool
     */
    public function isDeprecated()
    {
        return $this->deprecated;
    }

    /**
     * Message to use if this definition is deprecated.
     *
     * @param string $id Service id relying on this definition
     *
     * @return string
     */
    public function getDeprecationMessage($id)
    {
        return str_replace('%service_id%', $id, $this->deprecationTemplate);
    }

    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @param string|array $configurator A PHP callable
     *
     * @return $this
     */
    public function setConfigurator($configurator)
    {
        if (is_string($configurator) && strpos($configurator, '::') !== false) {
            $configurator = explode('::', $configurator, 2);
        }

        $this->trackChange('configurator');
        $this->configurator = $configurator;

        return $this;
    }

    /**
     * Gets the configurator to call after the service is fully initialized.
     *
     * @return callable|null The PHP callable to call
     */
    public function getConfigurator()
    {
        return $this->configurator;
    }

    /**
     * Sets types that will default to this definition.
     *
     * @param string[] $types
     *
     * @return $this
     *
     * @deprecated since version 3.3, to be removed in 4.0.
     */
    public function setAutowiringTypes(array $types)
    {
        @trigger_error('Autowiring-types are deprecated since Symfony 3.3 and will be removed in 4.0. Use aliases instead.', E_USER_DEPRECATED);

        $this->autowiringTypes = array();

        foreach ($types as $type) {
            $this->autowiringTypes[$type] = true;
        }

        return $this;
    }

    /**
     * Is the definition autowired?
     *
     * @return bool
     */
    public function isAutowired()
    {
        return $this->autowired;
    }

    /**
     * Sets autowired.
     *
     * @param bool $autowired
     *
     * @return $this
     */
    public function setAutowired($autowired)
    {
        $this->trackChange('autowired');
        $this->autowired = (bool) $autowired;

        return $this;
    }

    /**
     * Gets autowiring types that will default to this definition.
     *
     * @return string[]
     *
     * @deprecated since version 3.3, to be removed in 4.0.
     */
    public function getAutowiringTypes(/*$triggerDeprecation = true*/)
    {
        if (1 > func_num_args() || func_get_arg(0)) {
            @trigger_error('Autowiring-types are deprecated since Symfony 3.3 and will be removed in 4.0. Use aliases instead.', E_USER_DEPRECATED);
        }

        return array_keys($this->autowiringTypes);
    }

    /**
     * Adds a type that will default to this definition.
     *
     * @param string $type
     *
     * @return $this
     *
     * @deprecated since version 3.3, to be removed in 4.0.
     */
    public function addAutowiringType($type)
    {
        @trigger_error(sprintf('Autowiring-types are deprecated since Symfony 3.3 and will be removed in 4.0. Use aliases instead for "%s".', $type), E_USER_DEPRECATED);

        $this->autowiringTypes[$type] = true;

        return $this;
    }

    /**
     * Removes a type.
     *
     * @param string $type
     *
     * @return $this
     *
     * @deprecated since version 3.3, to be removed in 4.0.
     */
    public function removeAutowiringType($type)
    {
        @trigger_error(sprintf('Autowiring-types are deprecated since Symfony 3.3 and will be removed in 4.0. Use aliases instead for "%s".', $type), E_USER_DEPRECATED);

        unset($this->autowiringTypes[$type]);

        return $this;
    }

    /**
     * Will this definition default for the given type?
     *
     * @param string $type
     *
     * @return bool
     *
     * @deprecated since version 3.3, to be removed in 4.0.
     */
    public function hasAutowiringType($type)
    {
        @trigger_error(sprintf('Autowiring-types are deprecated since Symfony 3.3 and will be removed in 4.0. Use aliases instead for "%s".', $type), E_USER_DEPRECATED);

        return isset($this->autowiringTypes[$type]);
    }

    /**
     * Returns all changes tracked for the Definition object.
     *
     * @return array An array of changes for this Definition
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * Turn internal change tracking on or off.
     *
     * @param bool $trackChanges
     *
     * @return $this
     */
    public function setTrackChanges($trackChanges)
    {
        $this->trackChanges = $trackChanges;

        return $this;
    }

    private function trackChange($type)
    {
        if ($this->trackChanges) {
            $this->changes[$type] = true;
        }
    }
}
