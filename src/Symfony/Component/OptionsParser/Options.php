<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsParser;

use ArrayAccess;
use Iterator;
use OutOfBoundsException;
use Symfony\Component\OptionsParser\Exception\OptionDefinitionException;

/**
 * Container for resolving inter-dependent options.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Options implements ArrayAccess, Iterator
{
    /**
     * A list of option values and LazyOption instances.
     * @var array
     */
    private $options = array();

    /**
     * A list of Boolean locks for each LazyOption.
     * @var array
     */
    private $lock = array();

    /**
     * Whether the options have already been resolved.
     *
     * Once resolved, no new options can be added or changed anymore.
     *
     * @var Boolean
     */
    private $resolved = false;

    /**
     * Returns whether the given option exists.
     *
     * @param  string $option The option name.
     *
     * @return Boolean Whether the option exists.
     *
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($option)
    {
        return isset($this->options[$option]);
    }

    /**
     * Returns the value of the given option.
     *
     * After reading an option for the first time, this object becomes
     *
     * @param  string $option The option name.
     *
     * @return mixed The option value.
     *
     * @throws OutOfBoundsException      If the option does not exist.
     * @throws OptionDefinitionException If a cyclic dependency is detected
     *                                   between two lazy options.
     *
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($option)
    {
        if (!array_key_exists($option, $this->options)) {
            throw new OutOfBoundsException('The option "' . $option . '" does not exist');
        }

        $this->resolved = true;

        if ($this->options[$option] instanceof LazyOption) {
            if ($this->lock[$option]) {
                $conflicts = array_keys(array_filter($this->lock, function ($locked) {
                    return $locked;
                }));

                throw new OptionDefinitionException('The options "' . implode('", "', $conflicts) . '" have a cyclic dependency');
            }

            $this->lock[$option] = true;
            $this->options[$option] = $this->options[$option]->evaluate($this);
            $this->lock[$option] = false;
        }

        return $this->options[$option];
    }

    /**
     * Sets the value of a given option.
     *
     * @param string $option The name of the option.
     * @param mixed  $value  The value of the option. May be a closure with a
     *                       signature as defined in DefaultOptions::add().
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     *
     * @see DefaultOptions::add()
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($option, $value)
    {
        // Setting is not possible once an option is read, because then lazy
        // options could manipulate the state of the object, leading to
        // inconsistent results.
        if ($this->resolved) {
            throw new OptionDefinitionException('Options cannot be set after reading options');
        }

        $newValue = $value;

        // If an option is a closure that should be evaluated lazily, store it
        // inside a LazyOption instance.
        if ($newValue instanceof \Closure) {
            $reflClosure = new \ReflectionFunction($newValue);
            $params = $reflClosure->getParameters();
            $isLazyOption = count($params) >= 1 && null !== $params[0]->getClass() && __CLASS__ === $params[0]->getClass()->getName();

            if ($isLazyOption) {
                $currentValue = isset($this->options[$option]) ? $this->options[$option] : null;
                $newValue = new LazyOption($newValue, $currentValue);
            }

            // Store locks for lazy options to detect cyclic dependencies
            $this->lock[$option] = false;
        }

        $this->options[$option] = $newValue;
    }

    /**
     * Removes an option with the given name.
     *
     * @param string $option The option name.
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     *
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($option)
    {
        if ($this->resolved) {
            throw new OptionDefinitionException('Options cannot be unset after reading options');
        }

        unset($this->options[$option]);
        unset($this->allowedValues[$option]);
        unset($this->lock[$option]);
    }

    /**
     * @see Iterator::current()
     */
    public function current()
    {
        return $this->offsetGet($this->key());
    }

    /**
     * @see Iterator::next()
     */
    public function next()
    {
        next($this->options);
    }

    /**
     * @see Iterator::key()
     */
    public function key()
    {
        return key($this->options);
    }

    /**
     * @see Iterator::valid()
     */
    public function valid()
    {
        return null !== $this->key();
    }

    /**
     * @see Iterator::rewind()
     */
    public function rewind()
    {
        reset($this->options);
    }
}
