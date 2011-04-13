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

/**
 * InterfaceInjector is used for Interface Injection.
 *
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class InterfaceInjector
{
    private $class;
    private $calls = array();
    private $processedDefinitions = array();

    /**
     * Constructs interface injector by specifying the target class name
     *
     * @param string $class
     */
    public function __construct($class)
    {
        $this->class = $class;
    }

    /**
     * Returns the interface name
     *
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Sets the interface class
     *
     * @param string $class
     * @return void
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * Adds method calls if Definition is of required interface
     *
     * @param Definition $definition
     * @param string $class
     * @return void
     */
    public function processDefinition(Definition $definition, $class = null)
    {
        if (in_array($definition, $this->processedDefinitions, true)) {
            return;
        }

        $class = $class ?: $definition->getClass();

        if (!$this->supports($class)) {
            return;
        }

        foreach ($this->calls as $callback) {
            list($method, $arguments) = $callback;
            $definition->addMethodCall($method, $arguments);
        }

        $this->processedDefinitions[] = $definition;
    }

    /**
     * Inspects if current interface injector is to be used with a given class
     *
     * @param string $object
     * @return Boolean
     */
    public function supports($object)
    {
        if (is_string($object)) {
            if (!class_exists($object)) {
                return false;
            }

            $reflection = new \ReflectionClass($object);

            return $reflection->isSubClassOf($this->class)
                   || $object === $this->class;
        }

        if ( ! is_object($object)) {
            throw new InvalidArgumentException(sprintf("%s expects class or object, %s given", __METHOD__, substr(str_replace("\n", '', var_export($object, true)), 0, 10)));
        }

        return is_a($object, $this->class);
    }

    /**
     * Adds a method to call to be injected on any service implementing the interface.
     *
     * @param  string $method    The method name to call
     * @param  array  $arguments An array of arguments to pass to the method call
     *
     * @return InterfaceInjector The current instance
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
     * @return Boolean
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
     * @return  array An array of method calls
     */
    public function getMethodCalls()
    {
        return $this->calls;
    }

    /**
     * Merges another InterfaceInjector
     *
     * @param InterfaceInjector $injector
     */
    public function merge(InterfaceInjector $injector)
    {
        if ($this->class === $injector->getClass()) {
            foreach ($injector->getMethodCalls() as $call) {
                list ($method, $arguments) = $call;
                $this->addMethodCall($method, $arguments);
            }
        }
    }
}
