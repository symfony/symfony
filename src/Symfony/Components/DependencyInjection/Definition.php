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
 * Definition represents a service definition.
 *
 * @package    Symfony
 * @subpackage Components_DependencyInjection
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Definition
{
    protected $class;
    protected $file;
    protected $constructor;
    protected $shared;
    protected $arguments;
    protected $calls;
    protected $configurator;
    protected $annotations;

    /**
     * Constructor.
     *
     * @param string $class     The service class
     * @param array  $arguments An array of arguments to pass to the service constructor
     */
    public function __construct($class, array $arguments = array())
    {
        $this->class = $class;
        $this->arguments = $arguments;
        $this->calls = array();
        $this->shared = true;
        $this->annotations = array();
    }

    /**
     * Sets the constructor method.
     *
     * @param  string $method The method name
     *
     * @return Definition The current instance
     */
    public function setConstructor($method)
    {
        $this->constructor = $method;

        return $this;
    }

    /**
     * Gets the constructor method.
     *
     * @return Definition The constructor method name
     */
    public function getConstructor()
    {
        return $this->constructor;
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
     * Sets the constructor method.
     *
     * @return string The service class
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Sets the constructor arguments to pass to the service constructor.
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
     * Adds a constructor argument to pass to the service constructor.
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
     * Gets the constructor arguments to pass to the service constructor.
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
     * Gets the methods to call after service initialization.
     *
     * @return  array An array of method calls
     */
    public function getMethodCalls()
    {
        return $this->calls;
    }

    /**
     * Returns all annotations.
     *
     * @return array An array of annotations
     */
    public function getAnnotations()
    {
        return $this->annotations;
    }

    /**
     * Gets an annotation by name.
     *
     * @param  string $name       The annotation name
     *
     * @return array An array of attributes
     */
    public function getAnnotation($name)
    {
        if (!isset($this->annotations[$name])) {
            $this->annotations[$name] = array();
        }

        return $this->annotations[$name];
    }

    /**
     * Adds an annotation for this definition.
     *
     * @param  string $name       The annotation name
     * @param  array  $attributes An array of attributes
     *
     * @return Definition The current instance
     */
    public function addAnnotation($name, array $attributes = array())
    {
        if (!isset($this->annotations[$name])) {
            $this->annotations[$name] = array();
        }

        $this->annotations[$name][] = $attributes;

        return $this;
    }

    /**
     * Clears the annotation for this definition.
     *
     * @return Definition The current instance
     */
    public function clearAnnotations()
    {
        $this->annotations = array();

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
