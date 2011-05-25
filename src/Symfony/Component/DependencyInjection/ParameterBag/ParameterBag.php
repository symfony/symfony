<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\ParameterBag;

use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\ParameterCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParameterBag implements ParameterBagInterface
{
    protected $parameters;

    /**
     * Constructor.
     *
     * @param array $parameters An array of parameters
     */
    public function __construct(array $parameters = array())
    {
        $this->parameters = array();
        $this->add($parameters);
    }

    /**
     * Clears all parameters.
     */
    public function clear()
    {
        $this->parameters = array();
    }

    /**
     * Adds parameters to the service container parameters.
     *
     * @param array $parameters An array of parameters
     */
    public function add(array $parameters)
    {
        foreach ($parameters as $key => $value) {
            $this->parameters[strtolower($key)] = $value;
        }
    }

    /**
     * Gets the service container parameters.
     *
     * @return array An array of parameters
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * Gets a service container parameter.
     *
     * @param string $name The parameter name
     *
     * @return mixed  The parameter value
     *
     * @throws  ParameterNotFoundException if the parameter is not defined
     */
    public function get($name)
    {
        $name = strtolower($name);

        if (!array_key_exists($name, $this->parameters)) {
            throw new ParameterNotFoundException($name);
        }

        return $this->parameters[$name];
    }

    /**
     * Sets a service container parameter.
     *
     * @param string $name  The parameter name
     * @param mixed  $value The parameter value
     */
    public function set($name, $value)
    {
        $this->parameters[strtolower($name)] = $value;
    }

    /**
     * Returns true if a parameter name is defined.
     *
     * @param  string  $name       The parameter name
     *
     * @return Boolean true if the parameter name is defined, false otherwise
     */
    public function has($name)
    {
        return array_key_exists(strtolower($name), $this->parameters);
    }

    /**
     * Replaces parameter placeholders (%name%) by their values for all parameters.
     */
    public function resolve()
    {
        foreach ($this->parameters as $key => $value) {
            try {
                $this->parameters[$key] = $this->resolveValue($value);
            } catch (ParameterNotFoundException $e) {
                $e->setSourceKey($key);

                throw $e;
            }
        }
    }

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @param mixed $value A value
     * @param array $resolving An array of keys that are being resolved (used internally to detect circular references)
     *
     * @return mixed The resolved value
     *
     * @throws ParameterNotFoundException if a placeholder references a parameter that does not exist
     * @throws ParameterCircularReferenceException if a circular reference if detected
     * @throws RuntimeException when a given parameter has a type problem.
     */
    public function resolveValue($value, array $resolving = array())
    {
        if (is_array($value)) {
            $args = array();
            foreach ($value as $k => $v) {
                $args[$this->resolveValue($k, $resolving)] = $this->resolveValue($v, $resolving);
            }

            return $args;
        }

        if (!is_string($value)) {
            return $value;
        }

        return $this->resolveString($value, $resolving);
    }

    /**
     * Resolves parameters inside a string
     *
     * @param string $value     The string to resolve
     * @param array  $resolving An array of keys that are being resolved (used internally to detect circular references)
     *
     * @return string The resolved string
     *
     * @throws ParameterNotFoundException if a placeholder references a parameter that does not exist
     * @throws ParameterCircularReferenceException if a circular reference if detected
     * @throws RuntimeException when a given parameter has a type problem.
     */
    public function resolveString($value, array $resolving = array())
    {
        // we do this to deal with non string values (Boolean, integer, ...)
        // as the preg_replace_callback throw an exception when trying
        // a non-string in a parameter value
        if (preg_match('/^%([^%]+)%$/', $value, $match)) {
            $key = strtolower($match[1]);

            if (isset($resolving[$key])) {
                throw new ParameterCircularReferenceException(array_keys($resolving));
            }

            $resolving[$key] = true;

            return $this->resolveValue($this->get($key), $resolving);
        }

        $self = $this;
        return str_replace('%%', '%', preg_replace_callback('/(?<!%)%([^%]+)%/', function ($match) use ($self, $resolving) {
            $key = strtolower($match[1]);

            if (isset($resolving[$key])) {
                throw new ParameterCircularReferenceException(array_keys($resolving));
            }

            $resolved = $self->get($key);

            if (!is_string($resolved)) {
                throw new RuntimeException('A parameter cannot contain a non-string parameter.');
            }

            $resolving[$key] = true;

            return $self->resolveString($resolved, $resolving);
        }, $value));
    }
}
