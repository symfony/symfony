<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * ParameterBag is a container for key/value pairs.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ParameterBag implements \IteratorAggregate, \Countable
{
    /**
     * Parameter storage.
     */
    protected $parameters;

    /**
     * @param array $parameters An array of parameters
     */
    public function __construct(array $parameters = array())
    {
        $this->parameters = $parameters;
    }

    /**
     * Returns the parameters.
     *
     * @return array An array of parameters
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     */
    public function keys()
    {
        return array_keys($this->parameters);
    }

    /**
     * Replaces the current parameters by a new set.
     *
     * @param array $parameters An array of parameters
     */
    public function replace(array $parameters = array())
    {
        $this->parameters = $parameters;
    }

    /**
     * Adds parameters.
     *
     * @param array $parameters An array of parameters
     */
    public function add(array $parameters = array())
    {
        $this->parameters = array_replace($this->parameters, $parameters);
    }

    /**
     * Returns a parameter by name.
     *
     * Note: Finding deep items is deprecated since version 2.8, to be removed in 3.0.
     *
     * @param string $key     The key
     * @param mixed  $default The default value if the parameter key does not exist
     * @param bool   $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    public function get($key, $default = null, $deep = false)
    {
        if ($deep) {
            @trigger_error('Using paths to find deeper items in '.__METHOD__.' is deprecated since Symfony 2.8 and will be removed in 3.0. Filter the returned value in your own code instead.', E_USER_DEPRECATED);
        }

        if (!$deep || false === $pos = strpos($key, '[')) {
            return array_key_exists($key, $this->parameters) ? $this->parameters[$key] : $default;
        }

        $root = substr($key, 0, $pos);
        if (!array_key_exists($root, $this->parameters)) {
            return $default;
        }

        $value = $this->parameters[$root];
        $currentKey = null;
        for ($i = $pos, $c = \strlen($key); $i < $c; ++$i) {
            $char = $key[$i];

            if ('[' === $char) {
                if (null !== $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "[" at position %d.', $i));
                }

                $currentKey = '';
            } elseif (']' === $char) {
                if (null === $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "]" at position %d.', $i));
                }

                if (!\is_array($value) || !array_key_exists($currentKey, $value)) {
                    return $default;
                }

                $value = $value[$currentKey];
                $currentKey = null;
            } else {
                if (null === $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "%s" at position %d.', $char, $i));
                }

                $currentKey .= $char;
            }
        }

        if (null !== $currentKey) {
            throw new \InvalidArgumentException('Malformed path. Path must end with "]".');
        }

        return $value;
    }

    /**
     * Sets a parameter by name.
     *
     * @param string $key   The key
     * @param mixed  $value The value
     */
    public function set($key, $value)
    {
        $this->parameters[$key] = $value;
    }

    /**
     * Returns true if the parameter is defined.
     *
     * @param string $key The key
     *
     * @return bool true if the parameter exists, false otherwise
     */
    public function has($key)
    {
        return array_key_exists($key, $this->parameters);
    }

    /**
     * Removes a parameter.
     *
     * @param string $key The key
     */
    public function remove($key)
    {
        unset($this->parameters[$key]);
    }

    /**
     * Returns the alphabetic characters of the parameter value.
     *
     * @param string $key     The parameter key
     * @param string $default The default value if the parameter key does not exist
     * @param bool   $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return string The filtered value
     */
    public function getAlpha($key, $default = '', $deep = false)
    {
        return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default, $deep));
    }

    /**
     * Returns the alphabetic characters and digits of the parameter value.
     *
     * @param string $key     The parameter key
     * @param string $default The default value if the parameter key does not exist
     * @param bool   $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return string The filtered value
     */
    public function getAlnum($key, $default = '', $deep = false)
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default, $deep));
    }

    /**
     * Returns the digits of the parameter value.
     *
     * @param string $key     The parameter key
     * @param string $default The default value if the parameter key does not exist
     * @param bool   $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return string The filtered value
     */
    public function getDigits($key, $default = '', $deep = false)
    {
        // we need to remove - and + because they're allowed in the filter
        return str_replace(array('-', '+'), '', $this->filter($key, $default, FILTER_SANITIZE_NUMBER_INT, array(), $deep));
    }

    /**
     * Returns the parameter value converted to integer.
     *
     * @param string $key     The parameter key
     * @param int    $default The default value if the parameter key does not exist
     * @param bool   $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return int The filtered value
     */
    public function getInt($key, $default = 0, $deep = false)
    {
        return (int) $this->get($key, $default, $deep);
    }

    /**
     * Returns the parameter value converted to boolean.
     *
     * @param string $key     The parameter key
     * @param bool   $default The default value if the parameter key does not exist
     * @param bool   $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return bool The filtered value
     */
    public function getBoolean($key, $default = false, $deep = false)
    {
        return $this->filter($key, $default, FILTER_VALIDATE_BOOLEAN, array(), $deep);
    }

    /**
     * Filter key.
     *
     * @param string $key     Key
     * @param mixed  $default Default = null
     * @param int    $filter  FILTER_* constant
     * @param mixed  $options Filter options
     * @param bool   $deep    Default = false
     *
     * @see http://php.net/manual/en/function.filter-var.php
     *
     * @return mixed
     */
    public function filter($key, $default = null, $filter = FILTER_DEFAULT, $options = array(), $deep = false)
    {
        static $filters = null;

        if (null === $filters) {
            foreach (filter_list() as $tmp) {
                $filters[filter_id($tmp)] = 1;
            }
        }
        if (\is_bool($filter) || !isset($filters[$filter]) || \is_array($deep)) {
            @trigger_error('Passing the $deep boolean as 3rd argument to the '.__METHOD__.' method is deprecated since Symfony 2.8 and will be removed in 3.0. Remove it altogether as the $deep argument will be removed in 3.0.', E_USER_DEPRECATED);
            $tmp = $deep;
            $deep = $filter;
            $filter = $options;
            $options = $tmp;
        }

        $value = $this->get($key, $default, $deep);

        // Always turn $options into an array - this allows filter_var option shortcuts.
        if (!\is_array($options) && $options) {
            $options = array('flags' => $options);
        }

        // Add a convenience check for arrays.
        if (\is_array($value) && !isset($options['flags'])) {
            $options['flags'] = FILTER_REQUIRE_ARRAY;
        }

        return filter_var($value, $filter, $options);
    }

    /**
     * Returns an iterator for parameters.
     *
     * @return \ArrayIterator An \ArrayIterator instance
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->parameters);
    }

    /**
     * Returns the number of parameters.
     *
     * @return int The number of parameters
     */
    public function count()
    {
        return \count($this->parameters);
    }
}
