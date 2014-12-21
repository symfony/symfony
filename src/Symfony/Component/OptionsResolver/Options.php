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
     * A list of option values.
     *
     * @var array
     */
    private $options = array();

    /**
     * A list of normalizer closures.
     *
     * @var array
     */
    private $normalizers = array();

    /**
     * A list of closures for evaluating lazy options.
     *
     * @var array
     */
    private $lazy = array();

    /**
     * A list containing the currently locked options.
     *
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
     * @var bool
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
        unset($this->lazy[$option]);

        $this->overload($option, $value);
    }

    /**
     * Sets the normalizer for a given option.
     *
     * Normalizers should be closures with the following signature:
     *
     * <code>
     * function (Options $options, $value)
     * </code>
     *
     * This closure will be evaluated once the option is read using
     * {@link get()}. The closure has access to the resolved values of
     * other options through the passed {@link Options} instance.
     *
     * @param string   $option     The name of the option.
     * @param \Closure $normalizer The normalizer.
     *
     * @throws OptionDefinitionException If options have already been read.
     *                                   Once options are read, the container
     *                                   becomes immutable.
     */
    public function setNormalizer($option, \Closure $normalizer)
    {
        if ($this->reading) {
            throw new OptionDefinitionException('Normalizers cannot be added anymore once options have been read.');
        }

        $this->normalizers[$option] = $normalizer;
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
        $this->lazy = array();
        $this->normalizers = array();

        foreach ($options as $option => $value) {
            $this->overload($option, $value);
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

        // If an option is a closure that should be evaluated lazily, store it
        // in the "lazy" property.
        if ($value instanceof \Closure) {
            $reflClosure = new \ReflectionFunction($value);
            $params = $reflClosure->getParameters();

            if (isset($params[0]) && null !== ($class = $params[0]->getClass()) && __CLASS__ === $class->name) {
                // Initialize the option if no previous value exists
                if (!isset($this->options[$option])) {
                    $this->options[$option] = null;
                }

                // Ignore previous lazy options if the closure has no second parameter
                if (!isset($this->lazy[$option]) || !isset($params[1])) {
                    $this->lazy[$option] = array();
                }

                // Store closure for later evaluation
                $this->lazy[$option][] = $value;

                return;
            }
        }

        // Remove lazy options by default
        unset($this->lazy[$option]);

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
            throw new \OutOfBoundsException(sprintf('The option "%s" does not exist.', $option));
        }

        if (isset($this->lazy[$option])) {
            $this->resolve($option);
        }

        if (isset($this->normalizers[$option])) {
            $this->normalize($option);
        }

        return $this->options[$option];
    }

    /**
     * Returns whether the given option exists.
     *
     * @param string $option The option name.
     *
     * @return bool Whether the option exists.
     */
    public function has($option)
    {
        return array_key_exists($option, $this->options);
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
        unset($this->lazy[$option]);
        unset($this->normalizers[$option]);
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
        $this->lazy = array();
        $this->normalizers = array();
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

        // Performance-wise this is slightly better than
        // while (null !== $option = key($this->lazy))
        foreach ($this->lazy as $option => $closures) {
            // Double check, in case the option has already been resolved
            // by cascade in the previous cycles
            if (isset($this->lazy[$option])) {
                $this->resolve($option);
            }
        }

        foreach ($this->normalizers as $option => $normalizer) {
            if (isset($this->normalizers[$option])) {
                $this->normalize($option);
            }
        }

        return $this->options;
    }

    /**
     * Equivalent to {@link has()}.
     *
     * @param string $option The option name.
     *
     * @return bool Whether the option exists.
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
        return $this->get($this->key());
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
     * Evaluates the given lazy option.
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
        // The code duplication with normalize() exists for performance
        // reasons, in order to save a method call.
        // Remember that this method is potentially called a couple of thousand
        // times and needs to be as efficient as possible.
        if (isset($this->lock[$option])) {
            $conflicts = array();

            foreach ($this->lock as $option => $locked) {
                if ($locked) {
                    $conflicts[] = $option;
                }
            }

            throw new OptionDefinitionException(sprintf('The options "%s" have a cyclic dependency.', implode('", "', $conflicts)));
        }

        $this->lock[$option] = true;
        foreach ($this->lazy[$option] as $closure) {
            $this->options[$option] = $closure($this, $this->options[$option]);
        }
        unset($this->lock[$option]);

        // The option now isn't lazy anymore
        unset($this->lazy[$option]);
    }

    /**
     * Normalizes the given  option.
     *
     * The evaluated value is written into the options array.
     *
     * @param string $option The option to normalizer.
     *
     * @throws OptionDefinitionException If the option has a cyclic dependency
     *                                   on another option.
     */
    private function normalize($option)
    {
        // The code duplication with resolve() exists for performance
        // reasons, in order to save a method call.
        // Remember that this method is potentially called a couple of thousand
        // times and needs to be as efficient as possible.
        if (isset($this->lock[$option])) {
            $conflicts = array();

            foreach ($this->lock as $option => $locked) {
                if ($locked) {
                    $conflicts[] = $option;
                }
            }

            throw new OptionDefinitionException(sprintf('The options "%s" have a cyclic dependency.', implode('", "', $conflicts)));
        }

        /** @var \Closure $normalizer */
        $normalizer = $this->normalizers[$option];

        $this->lock[$option] = true;
        $this->options[$option] = $normalizer($this, array_key_exists($option, $this->options) ? $this->options[$option] : null);
        unset($this->lock[$option]);

        // The option is now normalized
        unset($this->normalizers[$option]);
    }
}
