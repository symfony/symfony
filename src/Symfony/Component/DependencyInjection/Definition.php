<?php

namespace Symfony\Component\DependencyInjection;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Definition represents a service definition.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Definition
{
    protected $class;
    protected $file;
    protected $factoryMethod;
    protected $factoryService;
    protected $shared;
    protected $arguments;
    protected $calls;
    protected $configurator;
    protected $tags;

    /**
     * Constructor.
     *
     * @param string $class     The service class
     * @param array  $arguments An array of arguments to pass to the service constructor
     */
    public function __construct($class = null, array $arguments = array())
    {
        $this->class = $class;
        $this->arguments = $arguments;
        $this->calls = array();
        $this->shared = true;
        $this->tags = array();
    }

    /**
     * Sets the factory method able to create an instance of this class.
     *
     * @param  string $method The method name
     *
     * @return Definition The current instance
     */
    public function setFactoryMethod($method)
    {
        $this->factoryMethod = $method;

        return $this;
    }

    /**
     * Gets the factory method.
     *
     * @return string The factory method name
     */
    public function getFactoryMethod()
    {
        return $this->factoryMethod;
    }

    /**
     * Sets the name of the service that acts as a factory using the constructor method.
     *
     * @param string $factoryService The factory service id
     *
     * @return Definition The current instance
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
     */
    public function getFactoryService()
    {
        return $this->factoryService;
    }

    /**
     * Sets the service class.
     *
     * @param  string $class The service class
     *
     * @return Definition The current instance
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * Sets the service class.
     *
     * @return string The service class
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Sets the arguments to pass to the service constructor/factory method.
     *
     * @param  array $arguments An array of arguments
     *
     * @return Definition The current instance
     */
    public function setArguments(array $arguments)
    {
        $this->arguments = $arguments;

        return $this;
    }

    /**
     * Adds an argument to pass to the service constructor/factory method.
     *
     * @param  mixed $argument An argument
     *
     * @return Definition The current instance
     */
    public function addArgument($argument)
    {
        $this->arguments[] = $argument;

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
     * Sets the methods to call after service initialization.
     *
     * @param  array $calls An array of method calls
     *
     * @return Definition The current instance
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
     * @param  string $method    The method name to call
     * @param  array  $arguments An array of arguments to pass to the method call
     *
     * @return Definition The current instance
     */
    public function addMethodCall($method, array $arguments = array())
    {
        $this->calls[] = array($method, $arguments);

        return $this;
    }

    /**
     * Removes a method to call after service initialization.
     *
     * @param  string $method    The method name to remove
     *
     * @return Definition The current instance
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
     * @param  string $method    The method name to search for
     *
     * @return boolean
     */
    public function hasMethodCall($method)
    {
        foreach ($this->calls as $i => $call) {
            if ($call[0] === $method) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the methods to call after service initialization.
     *
     * @return  array An array of method calls
     */
    public function getMethodCalls()
    {
        return $this->calls;
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
     * @param  string $name The tag name
     *
     * @return array An array of attributes
     */
    public function getTag($name)
    {
        if (!isset($this->tags[$name])) {
            $this->tags[$name] = array();
        }

        return $this->tags[$name];
    }

    /**
     * Adds a tag for this definition.
     *
     * @param  string $name       The tag name
     * @param  array  $attributes An array of attributes
     *
     * @return Definition The current instance
     */
    public function addTag($name, array $attributes = array())
    {
        if (!isset($this->tags[$name])) {
            $this->tags[$name] = array();
        }

        $this->tags[$name][] = $attributes;

        return $this;
    }

    /**
     * Clears the tags for this definition.
     *
     * @return Definition The current instance
     */
    public function clearTags()
    {
        $this->tags = array();

        return $this;
    }

    /**
     * Sets a file to require before creating the service.
     *
     * @param  string $file A full pathname to include
     *
     * @return Definition The current instance
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
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Sets if the service must be shared or not.
     *
     * @param  Boolean $shared Whether the service must be shared or not
     *
     * @return Definition The current instance
     */
    public function setShared($shared)
    {
        $this->shared = (Boolean) $shared;

        return $this;
    }

    /**
     * Returns true if the service must be shared.
     *
     * @return Boolean true if the service is shared, false otherwise
     */
    public function isShared()
    {
        return $this->shared;
    }

    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @param  mixed $callable A PHP callable
     *
     * @return Definition The current instance
     */
    public function setConfigurator($callable)
    {
        $this->configurator = $callable;

        return $this;
    }

    /**
     * Gets the configurator to call after the service is fully initialized.
     *
     * @return mixed The PHP callable to call
     */
    public function getConfigurator()
    {
        return $this->configurator;
    }
}
