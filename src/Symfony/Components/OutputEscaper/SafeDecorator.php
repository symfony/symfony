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
 * Marks a variable as being safe for output.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class SafeDecorator extends \ArrayIterator
{
    protected $value;

    /**
     * Constructor.
     *
     * @param mixed $value  The value to mark as safe
     */
    public function __construct($value)
    {
        $this->value = $value;

        if (is_array($value) || is_object($value)) {
            parent::__construct($value);
        }
    }

    public function __toString()
    {
        return (string) $this->value;
    }

    public function __get($key)
    {
        return $this->value->$key;
    }

    public function __set($key, $value)
    {
        $this->value->$key = $value;
    }

    public function __call($method, $arguments)
    {
        return call_user_func_array(array($this->value, $method), $arguments);
    }

    public function __isset($key)
    {
        return isset($this->value->$key);
    }

    public function __unset($key)
    {
        unset($this->value->$key);
    }

    /**
     * Returns the embedded value.
     *
     * @return mixed The embedded value
     */
    public function getValue()
    {
        return $this->value;
    }
}
