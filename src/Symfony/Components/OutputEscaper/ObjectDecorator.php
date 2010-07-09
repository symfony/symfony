<?php

namespace Symfony\Components\OutputEscaper;

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
 * @see        Escaper
 * @package    Symfony
 * @subpackage Components_OutputEscaper
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Mike Squire <mike@somosis.co.uk>
 */
class ObjectDecorator extends GetterDecorator
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
     * Returns the result of calling the get() method on the object, bypassing
     * any escaping, if that method exists.
     *
     * If there is not a callable get() method this will throw an exception.
     *
     * @param  string $key  The parameter to be passed to the get() get method
     *
     * @return mixed The unescaped value returned
     *
     * @throws \LogicException if the object does not have a callable get() method
     */
    public function getRaw($key)
    {
        if (!is_callable(array($this->value, 'get'))) {
            throw new \LogicException('Object does not have a callable get() method.');
        }

        return $this->value->get($key);
    }

    /**
     * Try to call decorated object __toString() method if exists.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->escape($this->escaper, (string) $this->value);
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
        return $this->escape($this->escaper, $this->value->$key);
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
}
