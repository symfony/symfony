<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\OptionsResolver;

use Symfony\Component\OptionsResolver\Exception\OptionDefinitionException;

/**
 * Container for resolving inter-dependent options.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Options implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * A list of option values and LazyOption instances.
     * @var array
     */
    private $options = array();

    /**
     * A list storing the names of all LazyOption instances as keys.
     * @var array
     */
    private $lazy = array();

    /**
     * A list of Boolean locks for each LazyOption.
     * @var array
     */
    private $lock = array();

    /**
     * Whether at least one option has already been read.
     *
     * Once read, the options cannot be changed anymore. This is
     * necessary in order to avoid inconsistencies during the resolving
     * process. If any option is changed after being read, all evaluated
     * lazy options that depend on this option would become invalid.
     *
     * @var Boolean
     */
    private $reading = false;

    /**
     * Sets the value of a given option.
     *
     * You can set lazy options by passing a closure with the following
     * signature:
     *
     * <code>
     * function (Options $options)
     * </code>
     *
     * This closure will be evaluated once the option is read using
     * {@link get()}. The closure has access to the resolved values of
     * other options through the passed {@link Options} instance.
     *
     * @param string $option The name of the option.
     * @param mixed  $value  The value of the option.
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     */
    public function set($option, $value)
    {
        // Setting is not possible once an option is read, because then lazy
        // options could manipulate the state of the object, leading to
        // inconsistent results.
        if ($this->reading) {
            throw new OptionDefinitionException('Options cannot be set anymore once options have been read.');
        }

        // Setting is equivalent to overloading while discarding the previous
        // option value
        unset($this->options[$option]);

        $this->overload($option, $value);
    }

    /**
     * Replaces the contents of the container with the given options.
     *
     * This method is a shortcut for {@link clear()} with subsequent
     * calls to {@link set()}.
     *
     * @param array $options The options to set.
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     */
    public function replace(array $options)
    {
        if ($this->reading) {
            throw new OptionDefinitionException('Options cannot be replaced anymore once options have been read.');
        }

        $this->options = array();

        foreach ($options as $option => $value) {
            $this->set($option, $value);
        }
    }

    /**
     * Overloads the value of a given option.
     *
     * Contrary to {@link set()}, this method keeps the previous default
     * value of the option so that you can access it if you pass a closure.
     * Passed closures should have the following signature:
     *
     * <code>
     * function (Options $options, $value)
     * </code>
     *
     * The second parameter passed to the closure is the current default
     * value of the option.
     *
     * @param string $option The option name.
     * @param mixed  $value  The option value.
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     */
    public function overload($option, $value)
    {
        if ($this->reading) {
            throw new OptionDefinitionException('Options cannot be overloaded anymore once options have been read.');
        }

        // Reset lazy flag and locks by default
        unset($this->lock[$option]);
        unset($this->lazy[$option]);

        // If an option is a closure that should be evaluated lazily, store it
        // inside a LazyOption instance.
        if (self::isEvaluatedLazily($value)) {
            $currentValue = isset($this->options[$option]) ? $this->options[$option] : null;
            $value = new LazyOption($value, $currentValue);

            // Store locks for lazy options to detect cyclic dependencies
            $this->lock[$option] = false;

            // Store which options are lazy for more efficient resolving
            $this->lazy[$option] = true;
        }

        $this->options[$option] = $value;
    }

    /**
     * Returns the value of the given option.
     *
     * If the option was a lazy option, it is evaluated now.
     *
     * @param string $option The option name.
     *
     * @return mixed The option value.
     *
     * @throws \OutOfBoundsException     If the option does not exist.
     * @throws OptionDefinitionException If a cyclic dependency is detected
     *                                   between two lazy options.
     */
    public function get($option)
    {
        $this->reading = true;

        if (!array_key_exists($option, $this->options)) {
            throw new \OutOfBoundsException('The option "' . $option . '" does not exist.');
        }

        if (isset($this->lazy[$option])) {
            $this->resolve($option);
        }

        return $this->options[$option];
    }

    /**
     * Returns whether the given option exists.
     *
     * @param string $option The option name.
     *
     * @return Boolean Whether the option exists.
     */
    public function has($option)
    {
        return isset($this->options[$option]);
    }

    /**
     * Removes the option with the given name.
     *
     * @param string $option The option name.
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     */
    public function remove($option)
    {
        if ($this->reading) {
            throw new OptionDefinitionException('Options cannot be removed anymore once options have been read.');
        }

        unset($this->options[$option]);
        unset($this->lock[$option]);
        unset($this->lazy[$option]);
    }

    /**
     * Removes all options.
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     */
    public function clear()
    {
        if ($this->reading) {
            throw new OptionDefinitionException('Options cannot be cleared anymore once options have been read.');
        }

        $this->options = array();
        $this->lock = array();
        $this->lazy = array();
    }

    /**
     * Returns the values of all options.
     *
     * Lazy options are evaluated at this point.
     *
     * @return array The option values.
     */
    public function all()
    {
        $this->reading = true;

        // Create a copy because resolve() modifies the array
        $lazy = $this->lazy;

        foreach ($lazy as $option => $isLazy) {
            $this->resolve($option);
        }

        return $this->options;
    }

    /**
     * Equivalent to {@link has()}.
     *
     * @param string $option The option name.
     *
     * @return Boolean Whether the option exists.
     *
     * @see \ArrayAccess::offsetExists()
     */
    public function offsetExists($option)
    {
        return $this->has($option);
    }

    /**
     * Equivalent to {@link get()}.
     *
     * @param string $option The option name.
     *
     * @return mixed The option value.
     *
     * @throws \OutOfBoundsException     If the option does not exist.
     * @throws OptionDefinitionException If a cyclic dependency is detected
     *                                   between two lazy options.
     *
     * @see \ArrayAccess::offsetGet()
     */
    public function offsetGet($option)
    {
        return $this->get($option);
    }

    /**
     * Equivalent to {@link set()}.
     *
     * @param string $option The name of the option.
     * @param mixed  $value  The value of the option. May be a closure with a
     *                       signature as defined in DefaultOptions::add().
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     *
     * @see \ArrayAccess::offsetSet()
     */
    public function offsetSet($option, $value)
    {
        $this->set($option, $value);
    }

    /**
     * Equivalent to {@link remove()}.
     *
     * @param string $option The option name.
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     *
     * @see \ArrayAccess::offsetUnset()
     */
    public function offsetUnset($option)
    {
        $this->remove($option);
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->offsetGet($this->key());
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        next($this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return key($this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return null !== $this->key();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        reset($this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->options);
    }

    /**
     * Evaluates the given option if it is a lazy option.
     *
     * The evaluated value is written into the options array. The closure for
     * evaluating the option is discarded afterwards.
     *
     * @param string $option The option to evaluate.
     *
     * @throws OptionDefinitionException If the option has a cyclic dependency
     *                                   on another option.
     */
    private function resolve($option)
    {
        if ($this->options[$option] instanceof LazyOption) {
            if ($this->lock[$option]) {
                $conflicts = array_keys(array_filter($this->lock, function ($locked) {
                    return $locked;
                }));

                throw new OptionDefinitionException('The options "' . implode('", "',
                    $conflicts) . '" have a cyclic dependency.');
            }

            $this->lock[$option] = true;
            $this->options[$option] = $this->options[$option]->evaluate($this);
            $this->lock[$option] = false;

            // The option now isn't lazy anymore
            unset($this->lazy[$option]);
        }
    }

    /**
     * Returns whether the option is a lazy option closure.
     *
     * Lazy option closure expect an {@link Options} instance
     * in their first parameter.
     *
     * @param mixed $value The option value to test.
     *
     * @return Boolean Whether it is a lazy option closure.
     */
    static private function isEvaluatedLazily($value)
    {
        if (!$value instanceof \Closure) {
            return false;
        }

        $reflClosure = new \ReflectionFunction($value);
        $params = $reflClosure->getParameters();

        if (count($params) < 1) {
            return false;
        }

        if (null === $params[0]->getClass()) {
            return false;
        }

        return __CLASS__ === $params[0]->getClass()->getName();
    }
}
