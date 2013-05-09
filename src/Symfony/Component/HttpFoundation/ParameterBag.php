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
 *
 * @api
 */
class ParameterBag implements \IteratorAggregate, \Countable
{
    /**
     * Parameter storage.
     *
     * @var array
     */
    protected $parameters;

    /**
     * Constructor.
     *
     * @param array $parameters An array of parameters
     *
     * @api
     */
    public function __construct(array $parameters = array())
    {
        $this->parameters = $parameters;
    }

    /**
     * Returns the parameters.
     *
     * @return array An array of parameters
     *
     * @api
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * Returns the parameter keys.
     *
     * @return array An array of parameter keys
     *
     * @api
     */
    public function keys()
    {
        return array_keys($this->parameters);
    }

    /**
     * Replaces the current parameters by a new set.
     *
     * @param array $parameters An array of parameters
     *
     * @api
     */
    public function replace(array $parameters = array())
    {
        $this->parameters = $parameters;
    }

    /**
     * Adds parameters.
     *
     * @param array $parameters An array of parameters
     *
     * @api
     */
    public function add(array $parameters = array())
    {
        $this->parameters = array_replace($this->parameters, $parameters);
    }

    /**
     * Returns a parameter by name.
     *
     * @param string  $path    The key
     * @param mixed   $default The default value if the parameter key does not exist
     * @param boolean $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return mixed
     *
     * @throws \InvalidArgumentException
     *
     * @api
     */
    public function get($path, $default = null, $deep = false)
    {
        if (!$deep) {
            return array_key_exists($path, $this->parameters) ? $this->parameters[$path] : $default;
        }

        $result = null;
        if (!$this->getParentAndKeyByPath($path, function($lastParent, $lastKey, $value) use (&$result) {
            $result = $value;
        })) {
            return $default;
        }

        return $result;
    }

    /**
     * Sets a parameter by name.
     *
     * @param string  $path  The key
     * @param mixed   $value The value
     * @param boolean $deep  If true, a path like foo[bar] will find deeper items
     *
     * @throws \RuntimeException
     *
     * @api
     */
    public function set($path, $value, $deep = false)
    {
        if (!$deep) {
            $this->parameters[$path] = $value;
        } else {
            $this->getParentAndKeyByPath($path, function(&$lastParent, $lastKey, $value) use ($value) {
                $lastParent[$lastKey] = $value;
            }, true);
        }
    }

    /**
     * Returns true if the parameter is defined.
     *
     * @param string  $path The key
     * @param boolean $deep If true, a path like foo[bar] will find deeper items
     *
     * @return Boolean true if the parameter exists, false otherwise
     *
     * @api
     */
    public function has($path, $deep = false)
    {
        if (!$deep) {
            return array_key_exists($path, $this->parameters);
        }

        return $this->getParentAndKeyByPath($path);
    }

    /**
     * Removes a parameter.
     *
     * @param string  $path The key
     * @param boolean $deep If true, a path like foo[bar] will find deeper items
     *
     * @api
     */
    public function remove($path, $deep = false)
    {
        if (!$deep) {
            unset($this->parameters[$path]);
        } else {
            $this->getParentAndKeyByPath($path, function(&$lastParent, &$lastKey, $value) {
                unset($lastParent[$lastKey]);
            });
        }
    }

    /**
     * Returns the alphabetic characters of the parameter value.
     *
     * @param string  $key     The parameter key
     * @param mixed   $default The default value if the parameter key does not exist
     * @param boolean $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return string The filtered value
     *
     * @api
     */
    public function getAlpha($key, $default = '', $deep = false)
    {
        return preg_replace('/[^[:alpha:]]/', '', $this->get($key, $default, $deep));
    }

    /**
     * Returns the alphabetic characters and digits of the parameter value.
     *
     * @param string  $key     The parameter key
     * @param mixed   $default The default value if the parameter key does not exist
     * @param boolean $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return string The filtered value
     *
     * @api
     */
    public function getAlnum($key, $default = '', $deep = false)
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->get($key, $default, $deep));
    }

    /**
     * Returns the digits of the parameter value.
     *
     * @param string  $key     The parameter key
     * @param mixed   $default The default value if the parameter key does not exist
     * @param boolean $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return string The filtered value
     *
     * @api
     */
    public function getDigits($key, $default = '', $deep = false)
    {
        // we need to remove - and + because they're allowed in the filter
        return str_replace(array('-', '+'), '', $this->filter($key, $default, $deep, FILTER_SANITIZE_NUMBER_INT));
    }

    /**
     * Returns the parameter value converted to integer.
     *
     * @param string  $key     The parameter key
     * @param mixed   $default The default value if the parameter key does not exist
     * @param boolean $deep    If true, a path like foo[bar] will find deeper items
     *
     * @return integer The filtered value
     *
     * @api
     */
    public function getInt($key, $default = 0, $deep = false)
    {
        return (int) $this->get($key, $default, $deep);
    }

    /**
     * Filter key.
     *
     * @param string  $key     Key.
     * @param mixed   $default Default = null.
     * @param boolean $deep    Default = false.
     * @param integer $filter  FILTER_* constant.
     * @param mixed   $options Filter options.
     *
     * @see http://php.net/manual/en/function.filter-var.php
     *
     * @return mixed
     */
    public function filter($key, $default = null, $deep = false, $filter=FILTER_DEFAULT, $options=array())
    {
        $value = $this->get($key, $default, $deep);

        // Always turn $options into an array - this allows filter_var option shortcuts.
        if (!is_array($options) && $options) {
            $options = array('flags' => $options);
        }

        // Add a convenience check for arrays.
        if (is_array($value) && !isset($options['flags'])) {
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
        return count($this->parameters);
    }

    /**
     * Allows to find the last parent and key for a given path within the parameters.
     *
     * @param string      $path       A path like foo[bar]
     * @param null|array  $lastParent A call-by-reference parameter for the last parent
     * @param null|string $lastKey    A call-by-reference parameter for the last key
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @return boolean True if the path was found, false otherwise
     */
    private function getParentAndKeyByPath($path, \Closure $foundCallback = null, $createPath = false)
    {
        $parameters = $this->parameters;

        $pos = strpos($path, '[');
        if ($pos === false) {
            $pos = strlen($path);
        }
        $root = substr($path, 0, $pos);
        if (!array_key_exists($root, $parameters)) {
            if ($createPath) {
                $parameters[$root] = array();
            } else {
                return false;
            }
        }

        $lastParent = &$parameters;
        $lastKey = $root;
        $currentKey = null;
        for ($i = $pos, $c = strlen($path); $i < $c; $i++) {
            $char = $path[$i];

            if ('[' === $char) {
                if (null !== $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "[" at position %d.', $i));
                }

                $currentKey = '';
            } elseif (']' === $char) {
                if (null === $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "]" at position %d.', $i));
                }

                if (!$createPath && (!is_array($lastParent[$lastKey]) || !array_key_exists($currentKey, $lastParent[$lastKey]))) {
                    return false;
                }

                if ($createPath) {
                    if (!is_array($lastParent[$lastKey])) {
                        throw new \RuntimeException("Cannot set deep value as $lastKey is not an array");
                    }
                    if (!array_key_exists($currentKey, $lastParent[$lastKey])) {
                        $lastParent[$lastKey][$currentKey] = array();
                    }
                }

                $lastParent = &$lastParent[$lastKey];
                $lastKey = $currentKey;
                $currentKey = null;
            } else {
                if (null === $currentKey) {
                    throw new \InvalidArgumentException(sprintf('Malformed path. Unexpected "%s" at position %d.', $char, $i));
                }

                $currentKey .= $char;
            }
        }

        if (null !== $currentKey) {
            throw new \InvalidArgumentException(sprintf('Malformed path. Path must end with "]".'));
        }

        if ($foundCallback) {
            $foundCallback($lastParent, $lastKey, $lastParent[$lastKey]);
        }
        $this->parameters = $parameters;

        return true;
    }

}
