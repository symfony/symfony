<?php

namespace Symfony\Component\OutputEscaper;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Output escaping object decorator that intercepts all method calls and escapes
 * their return values.
 *
 * @see    Escaper
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Mike Squire <mike@somosis.co.uk>
 */
class ObjectDecorator extends BaseEscaper implements \ArrayAccess, \Countable
{
    /**
     * Magic PHP method that intercepts method calls, calls them on the objects
     * that is being escaped and escapes the result.
     *
     * The calling of the method is changed slightly to accommodate passing a
     * specific escaping strategy. An additional parameter is appended to the
     * argument list which is the escaping strategy. The decorator will remove
     * and use this parameter as the escaping strategy if it begins with 'esc_'.
     *
     * For example if an object, $o, implements methods a() and b($arg):
     *
     *   $o->a()                // Escapes the return value of a()
     *   $o->a('esc_raw')       // Uses the escaping strategy 'raw' with a()
     *   $o->b('a')             // Escapes the return value of b('a')
     *   $o->b('a', 'esc_raw'); // Uses the escaping strategy 'raw' with b('a')
     *
     * @param  string $method  The method on the object to be called
     * @param  array  $args    An array of arguments to be passed to the method
     *
     * @return mixed The escaped value returned by the method
     */
    public function __call($method, $args)
    {
        if (count($args) > 0) {
            $escaper = $args[count($args) - 1];
            if (is_string($escaper) && 'esc_' === substr($escaper, 0, 4)) {
                $escaper = substr($escaper, 4);

                array_pop($args);
            } else {
                $escaper = $this->escaper;
            }
        } else {
            $escaper = $this->escaper;
        }

        $value = call_user_func_array(array($this->value, $method), $args);

        return Escaper::escape($escaper, $value);
    }

    /**
     * Try to call decorated object __toString() method if exists.
     *
     * @return string
     */
    public function __toString()
    {
        return Escaper::escape($this->escaper, (string) $this->value);
    }

    /**
     * Gets a value from the escaper.
     *
     * @param string $key The name of the value to get
     *
     * @return mixed The value from the wrapped object
     */
    public function __get($key)
    {
        return Escaper::escape($this->escaper, $this->value->$key);
    }

    /**
     * Checks whether a value is set on the wrapped object.
     *
     * @param string $key The name of the value to check
     *
     * @return boolean Returns true if the value is set
     */
    public function __isset($key)
    {
        return isset($this->value->$key);
    }

    /**
     * Escapes an object property using the specified escaper.
     *
     * @param string $key     The object property name
     * @param string $escaper The escaping strategy prefixed with esc_ (see __call())
     */
    public function getEscapedProperty($key, $escaper)
    {
        return Escaper::escape(substr($escaper, 4), $this->value->$key);
    }

    /**
     * Returns true if the supplied offset isset in the array (as required by the ArrayAccess interface).
     *
     * @param  string $offset  The offset of the value to check existence of
     *
     * @return bool true if the offset isset; false otherwise
     */
    public function offsetExists($offset)
    {
        return isset($this->value[$offset]);
    }

    /**
     * Returns the element associated with the offset supplied (as required by the ArrayAccess interface).
     *
     * @param  string $offset  The offset of the value to get
     *
     * @return mixed The escaped value
     */
    public function offsetGet($offset)
    {
        return Escaper::escape($this->escaper, $this->value[$offset]);
    }

    /**
     * Throws an exception saying that values cannot be set (this method is
     * required for the ArrayAccess interface).
     *
     * This (and the other Escaper classes) are designed to be read only
     * so this is an illegal operation.
     *
     * @param  string $offset  (ignored)
     * @param  string $value   (ignored)
     *
     * @throws \LogicException When trying to set values
     */
    public function offsetSet($offset, $value)
    {
        throw new \LogicException('Cannot set values.');
    }

    /**
     * Throws an exception saying that values cannot be unset (this method is
     * required for the ArrayAccess interface).
     *
     * This (and the other Escaper classes) are designed to be read only
     * so this is an illegal operation.
     *
     * @param  string $offset  (ignored)
     *
     * @throws \LogicException When trying to unset values
     */
    public function offsetUnset($offset)
    {
        throw new \LogicException('Cannot unset values.');
    }

    /**
     * Escapes a key from the array using the specified escaper.
     *
     * @param string $key     The array key
     * @param string $escaper The escaping strategy prefixed with esc_ (see __call())
     */
    public function getEscapedKey($key, $escaper)
    {
        return Escaper::escape(substr($escaper, 4), $this->value[$key]);
    }

    /**
     * Returns the size of the array (are required by the Countable interface).
     *
     * @return int The size of the array
     */
    public function count()
    {
        if ($this->value instanceof \Countable) {
            return count($this->value);
        }

        return call_user_func_array(array($this->value, 'count'), func_get_args());
    }
}
