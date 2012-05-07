<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use ArrayAccess;
use Iterator;
use OutOfBoundsException;
use Symfony\Component\Form\Exception\OptionDefinitionException;

/**
 * Container for resolving inter-dependent options.
 *
 * Options are a common pattern for resolved classes in PHP. Avoiding the
 * problems related to this approach is however a non-trivial task. Usually,
 * both classes and subclasses should be able to set default option values.
 * These default options should be overridden by the options passed to the
 * constructor. Last but not least, the (default) values of some options may
 * depend on the values of other options, which themselves may depend on other
 * options.
 *
 * This class resolves these problems. You can use it in your classes by
 * implementing the following pattern:
 *
 * <code>
 * class Car
 * {
 *     protected $options;
 *
 *     public function __construct(array $options)
 *     {
 *         $_options = new Options();
 *         $this->addDefaultOptions($_options);
 *
 *         $this->options = $_options->resolve($options);
 *     }
 *
 *     protected function addDefaultOptions(Options $options)
 *     {
 *         $options->add(array(
 *             'make' => 'VW',
 *             'year' => '1999',
 *         ));
 *     }
 * }
 *
 * $car = new Car(array(
 *     'make' => 'Mercedes',
 *     'year' => 2005,
 * ));
 * </code>
 *
 * By calling add(), new default options are added to the container. The method
 * resolve() accepts an array of options passed by the user that are matched
 * against the allowed options. If any option is not recognized, an exception
 * is thrown. Finally, resolve() returns the merged default and user options.
 *
 * You can now easily add or override options in subclasses:
 *
 * <code>
 * class Renault extends Car
 * {
 *     protected function addDefaultOptions(Options $options)
 *     {
 *         parent::addDefaultOptions($options);
 *
 *         $options->add(array(
 *             'make' => 'Renault',
 *             'gear' => 'auto',
 *         ));
 *     }
 * }
 *
 * $renault = new Renault(array(
 *     'year' => 1997,
 *     'gear' => 'manual'
 * ));
 * </code>
 *
 * IMPORTANT: parent::addDefaultOptions() must always be called before adding
 * new options!
 *
 * In the previous example, it makes sense to restrict the option "gear" to
 * a set of allowed values:
 *
 * <code>
 * class Renault extends Car
 * {
 *     protected function addDefaultOptions(Options $options)
 *     {
 *         // ... like above ...
 *
 *         $options->addAllowedValues(array(
 *             'gear' => array('auto', 'manual'),
 *         ));
 *     }
 * }
 *
 * // Fails!
 * $renault = new Renault(array(
 *     'gear' => 'v6',
 * ));
 * </code>
 *
 * Now it is impossible to pass a value in the "gear" option that is not
 * expected.
 *
 * Last but not least, you can define options that depend on other options.
 * For example, depending on the "make" you could preset the country that the
 * car is registered in.
 *
 * <code>
 * class Car
 * {
 *     protected function addDefaultOptions(Options $options)
 *     {
 *         $options->add(array(
 *             'make' => 'VW',
 *             'year' => '1999',
 *             'country' => function (Options $options) {
 *                 if ('VW' === $options['make']) {
 *                     return 'DE';
 *                 }
 *
 *                 return null;
 *             },
 *         ));
 *     }
 * }
 *
 * $car = new Car(array(
 *     'make' => 'VW', // => "country" is "DE"
 * ));
 * </code>
 *
 * When overriding an option with a closure in subclasses, you can make use of
 * the second parameter $parentValue in which the value defined by the parent
 * class is stored.
 *
 * <code>
 * class Renault extends Car
 * {
 *     protected function addDefaultOptions(Options $options)
 *     {
 *         $options->add(array(
 *             'country' => function (Options $options, $parentValue) {
 *                 if ('Renault' === $options['make']) {
 *                     return 'FR';
 *                 }
 *
 *                 return $parentValue;
 *             },
 *         ));
 *     }
 * }
 *
 * $renault = new Renault(array(
 *     'make' => 'VW', // => "country" is still "DE"
 * ));
 * </code>
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
     * Returns the names of all defined options.
     *
     * @return array An array of option names.
     */
    public function getNames()
    {
        return array_keys($this->options);
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
