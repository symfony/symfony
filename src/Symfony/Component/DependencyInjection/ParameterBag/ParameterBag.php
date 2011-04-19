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
     * @throws  \InvalidArgumentException if the parameter is not defined
     */
    public function get($name)
    {
        $name = strtolower($name);

        if (!array_key_exists($name, $this->parameters)) {
            throw new \InvalidArgumentException(sprintf('The parameter "%s" must be defined.', $name));
        }

        return $this->parameters[$name];
    }

    /**
     * Sets a service container parameter.
     *
     * @param string $name       The parameter name
     * @param mixed  $parameters The parameter value
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
            $this->parameters[$key] = $this->resolveValue($value);
        }
    }

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @param  mixed $value A value
     *
     * @throws \InvalidArgumentException if a placeholder references a parameter that does not exist
     */
    public function resolveValue($value)
    {
        if (is_array($value)) {
            $args = array();
            foreach ($value as $k => $v) {
                $args[$this->resolveValue($k)] = $this->resolveValue($v);
            }

            return $args;
        }

        if (!is_string($value)) {
            return $value;
        }

        if (preg_match('/^%([^%]+)%$/', $value, $match)) {
            // we do this to deal with non string values (Boolean, integer, ...)
            // the preg_replace_callback converts them to strings
            return $this->get(strtolower($match[1]));
        }

        return str_replace('%%', '%', preg_replace_callback(array('/(?<!%)%([^%]+)%/'), array($this, 'resolveValueCallback'), $value));
    }

    /**
     * Value callback
     *
     * @see resolveValue
     *
     * @param array $match
     * @return string
     */
    private function resolveValueCallback($match)
    {
        return $this->get(strtolower($match[1]));
    }
}
