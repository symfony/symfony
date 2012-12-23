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
 *
 * @api
 */
class Definition
{
    private $class;
    private $file;
    private $factoryClass;
    private $factoryMethod;
    private $factoryService;
    private $scope;
    private $properties;
    private $calls;
    private $configurator;
    private $tags;
    private $public;
    private $synthetic;
    private $abstract;

    protected $arguments;

    /**
     * Constructor.
     *
     * @param string $class     The service class
     * @param array  $arguments An array of arguments to pass to the service constructor
     *
     * @api
     */
    public function __construct($class = null, array $arguments = array())
    {
        $this->class = $class;
        $this->arguments = $arguments;
        $this->calls = array();
        $this->scope = ContainerInterface::SCOPE_CONTAINER;
        $this->tags = array();
        $this->public = true;
        $this->synthetic = false;
        $this->abstract = false;
        $this->properties = array();
    }

    /**
     * Sets the name of the class that acts as a factory using the factory method,
     * which will be invoked statically.
     *
     * @param string $factoryClass The factory class name
     *
     * @return Definition The current instance
     *
     * @api
     */
    public function setFactoryClass($factoryClass)
    {
        $this->factoryClass = $factoryClass;

        return $this;
    }

    /**
     * Gets the factory class.
     *
     * @return string The factory class name
     *
     * @api
     */
    public function getFactoryClass()
    {
        return $this->factoryClass;
    }

    /**
     * Sets the factory method able to create an instance of this class.
     *
     * @param string $factoryMethod The factory method name
     *
     * @return Definition The current instance
     *
     * @api
     */
    public function setFactoryMethod($factoryMethod)
    {
        $this->factoryMethod = $factoryMethod;

        return $this;
    }

    /**
     * Gets the factory method.
     *
     * @return string The factory method name
     *
     * @api
     */
    public function getFactoryMethod()
    {
        return $this->factoryMethod;
    }

    /**
     * Sets the name of the service that acts as a factory using the factory method.
     *
     * @param string $factoryService The factory service id
     *
     * @return Definition The current instance
     *
     * @api
     */
    public function setFactoryService($factoryService)
    {
        $this->factoryService = $factoryService;

        return $this;
    }

    /**
     * Gets the factory service id.
     *
     * @return string The factory service id
     *
     * @api
     */
    public function getFactoryService()
    {
        return $this->factoryService;
    }

    /**
     * Sets the service class.
     *
     * @param string $class The service class
     *
     * @return Definition The current instance
     *
     * @api
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Gets the service class.
     *
     * @return string The service class
     *
     * @api
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
     * @return Definition The current instance
     *
     * @api
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * @api
     */
    public function setProperties(array $properties)
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @api
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @api
     */
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
     * @return Definition The current instance
     *
     * @api
     */
    public function addArgument($argument)
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * Sets a specific argument
     *
     * @param integer $index
     * @param mixed   $argument
     *
     * @return Definition The current instance
     *
     * @throws OutOfBoundsException When the replaced argument does not exist
     *
     * @api
     */
    public function replaceArgument($index, $argument)
    {
        if ($index < 0 || $index > count($this->arguments) - 1) {
            throw new OutOfBoundsException(sprintf('The index "%d" is not in the range [0, %d].', $index, count($this->arguments) - 1));
        }

        $this->arguments[$index] = $argument;

        return $this;
    }

    /**
     * Gets the arguments to pass to the service constructor/factory method.
     *
     * @return array The array of arguments
     *
     * @api
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * Gets an argument to pass to the service constructor/factory method.
     *
     * @param integer $index
     *
     * @return mixed The argument value
     *
     * @throws OutOfBoundsException When the argument does not exist
     *
     * @api
     */
    public function getArgument($index)
    {
        if ($index < 0 || $index > count($this->arguments) - 1) {
            throw new OutOfBoundsException(sprintf('The index "%d" is not in the range [0, %d].', $index, count($this->arguments) - 1));
        }

        return $this->arguments[$index];
    }

    /**
     * Sets the methods to call after service initialization.
     *
     * @param array $calls An array of method calls
     *
     * @return Definition The current instance
     *
     * @api
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
     * @return Definition The current instance
     *
     * @throws InvalidArgumentException on empty $method param
     *
     * @api
     */
    public function addMethodCall($method, array $arguments = array())
    {
        if (empty($method)) {
            throw new InvalidArgumentException(sprintf('Method name cannot be empty.'));
        }
        $this->calls[] = array($method, $arguments);

        return $this;
    }

    /**
     * Removes a method to call after service initialization.
     *
     * @param string $method The method name to remove
     *
     * @return Definition The current instance
     *
     * @api
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
     * @return Boolean
     *
     * @api
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
     *
     * @api
     */
    public function getMethodCalls()
    {
        return $this->calls;
    }

    /**
     * Sets tags for this definition
     *
     * @param array $tags
     *
     * @return Definition the current instance
     *
     * @api
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
     *
     * @api
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
     *
     * @api
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
     * @return Definition The current instance
     *
     * @api
     */
    public function addTag($name, array $attributes = array())
    {
        $this->tags[$name][] = $attributes;

        return $this;
    }

    /**
     * Whether this definition has a tag with the given name
     *
     * @param string $name
     *
     * @return Boolean
     *
     * @api
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
     * @return Definition
     */
    public function clearTag($name)
    {
        if (isset($this->tags[$name])) {
            unset($this->tags[$name]);
        }

        return $this;
    }

    /**
     * Clears the tags for this definition.
     *
     * @return Definition The current instance
     *
     * @api
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
     * @return Definition The current instance
     *
     * @api
     */
    public function setFile($file)
    {
        $this->file = $file;

        return $this;
    }

    /**
     * Gets the file to require before creating the service.
     *
     * @return string The full pathname to include
     *
     * @api
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Sets the scope of the service
     *
     * @param string $scope Whether the service must be shared or not
     *
     * @return Definition The current instance
     *
     * @api
     */
    public function setScope($scope)
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * Returns the scope of the service
     *
     * @return string
     *
     * @api
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * Sets the visibility of this service.
     *
     * @param Boolean $boolean
     *
     * @return Definition The current instance
     *
     * @api
     */
    public function setPublic($boolean)
    {
        $this->public = (Boolean) $boolean;

        return $this;
    }

    /**
     * Whether this service is public facing
     *
     * @return Boolean
     *
     * @api
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * Sets whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @param Boolean $boolean
     *
     * @return Definition the current instance
     *
     * @api
     */
    public function setSynthetic($boolean)
    {
        $this->synthetic = (Boolean) $boolean;

        return $this;
    }

    /**
     * Whether this definition is synthetic, that is not constructed by the
     * container, but dynamically injected.
     *
     * @return Boolean
     *
     * @api
     */
    public function isSynthetic()
    {
        return $this->synthetic;
    }

    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @param Boolean $boolean
     *
     * @return Definition the current instance
     *
     * @api
     */
    public function setAbstract($boolean)
    {
        $this->abstract = (Boolean) $boolean;

        return $this;
    }

    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @return Boolean
     *
     * @api
     */
    public function isAbstract()
    {
        return $this->abstract;
    }

    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @param callable $callable A PHP callable
     *
     * @return Definition The current instance
     *
     * @api
     */
    public function setConfigurator($callable)
    {
        $this->configurator = $callable;

        return $this;
    }

    /**
     * Gets the configurator to call after the service is fully initialized.
     *
     * @return callable The PHP callable to call
     *
     * @api
     */
    public function getConfigurator()
    {
        return $this->configurator;
    }
}
