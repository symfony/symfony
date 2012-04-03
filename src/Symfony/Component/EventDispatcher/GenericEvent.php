<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * Event encapsulation class.
 *
 * Encapsulates events thus decoupling the observer from the subject they encapsulate.
 *
 * @author Drak <drak@zikula.org>
 */
class GenericEvent extends Event implements \ArrayAccess
{
    /**
     * Observer pattern subject.
     *
     * @var mixed usually object or callable
     */
    protected $subject;

    /**
     * Array of arguments.
     *
     * @var array
     */
    protected $args;

    /**
     * Storage for any process type events.
     *
     * @var mixed
     */
    protected $data;

    /**
     * Encapsulate an event with $subject, $args, and $data.
     *
     * @param mixed  $subject Usually an object or other PHP callable.
     * @param array  $args    Arguments to store in the event.
     * @param mixed  $data    Convenience argument of data for optional processing.
     */
    public function __construct($subject = null, array $args = array(), $data = null)
    {
        $this->subject = $subject;
        $this->args = $args;
        $this->data = $data;
    }

    /**
     * Getter for subject property.
     *
     * @return mixed $subject The observer subject.
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Get argument by key.
     *
     * @param string $key Key.
     *
     * @throws \InvalidArgumentException If key is not found.
     *
     * @return mixed Contents of array key.
     */
    public function getArg($key)
    {
        if ($this->hasArg($key)) {
            return $this->args[$key];
        }

        throw new \InvalidArgumentException(sprintf('%s not found in %s', $key, $this->getName()));
    }

    /**
     * Add argument to event.
     *
     * @param string $key   Argument name.
     * @param mixed  $value Value.
     *
     * @return GenericEvent
     */
    public function setArg($key, $value)
    {
        $this->args[$key] = $value;

        return $this;
    }

    /**
     * Getter for all arguments.
     *
     * @return array
     */
    public function getArgs()
    {
        return $this->args;
    }

    /**
     * Set args property.
     *
     * @param array $args Arguments.
     *
     * @return GenericEvent
     */
    public function setArgs(array $args = array())
    {
        $this->args = $args;

        return $this;
    }

    /**
     * Has argument.
     *
     * @param string $key Key of arguments array.
     *
     * @return boolean
     */
    public function hasArg($key)
    {
        return array_key_exists($key, $this->args);
    }

    /**
     * Getter for Data property.
     *
     * @return mixed Data property.
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set data.
     *
     * @param mixed $data Data to be saved.
     *
     * @return GenericEvent
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * ArrayAccess for argument getter.
     *
     * @param string $key Array key.
     *
     * @throws \InvalidArgumentException If key does not exist in $this->args.
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        if ($this->hasArg($key)) {
            return $this->args[$key];
        }

        throw new \InvalidArgumentException(sprintf('The requested key %s does not exist', $key));
    }

    /**
     * ArrayAccess for argument setter.
     *
     * @param string $key   Array key to set.
     * @param mixed  $value Value.
     *
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->setArg($key, $value);
    }

    /**
     * ArrayAccess for unset argument.
     *
     * @param string $key Array key.
     *
     * @return void
     */
    public function offsetUnset($key)
    {
        if ($this->hasArg($key)) {
            unset($this->args[$key]);
        }
    }

    /**
     * ArrayAccess has argument.
     *
     * @param string $key Array key.
     *
     * @return boolean
     */
    public function offsetExists($key)
    {
        return $this->hasArg($key);
    }
}
