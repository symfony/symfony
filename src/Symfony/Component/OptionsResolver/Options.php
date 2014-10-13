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

use Symfony\Component\OptionsResolver\Exception\InvalidArgumentException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Exception\OptionDefinitionException;

/**
 * Container for resolving inter-dependent options.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Options implements \ArrayAccess, \Iterator, \Countable
{
    /**
     * Whether to format {@link \DateTime} objects as RFC-3339 dates
     * during exceptions ("Y-m-d H:i:s").
     *
     * @var int
     */
    const PRETTY_DATE = 1;

    /**
     * Whether to cast objects with a "__toString()" method to strings during exceptions.
     *
     * @var int
     */
    const OBJECT_TO_STRING = 2;

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
     * Merges options with an array of default values and throws an exception if
     * any of the options does not exist.
     *
     * @param array                       $options  A list of option names and
     *                                              values
     * @param array|Options|OptionsConfig $defaults The accepted options and
     *                                              their default values
     *
     * @return array The merged and validated options
     *
     * @throws InvalidOptionsException  If any of the options is not present in
     *                                  the defaults array
     * @throws InvalidArgumentException If the defaults are invalid
     *
     * @since 2.6
     */
    public static function resolve(array $options, $defaults)
    {
        if (is_array($defaults)) {
            self::validateNames($options, $defaults, true);

            return array_replace($defaults, $options);
        }

        if ($defaults instanceof self) {
            self::validateNames($options, $defaults->options, true);

            // Make sure this method can be called multiple times
            $combinedOptions = clone $defaults;

            // Override options set by the user
            foreach ($options as $option => $value) {
                $combinedOptions->set($option, $value);
            }

            // Resolve options
            return $combinedOptions->all();
        }

        if ($defaults instanceof OptionsConfig) {
            self::validateNames($options, $defaults->knownOptions, true);
            self::validateRequired($options, $defaults->requiredOptions, true);

            // Make sure this method can be called multiple times
            $combinedOptions = clone $defaults->defaultOptions;

            // Override options set by the user
            foreach ($options as $option => $value) {
                $combinedOptions->set($option, $value);
            }

            // Resolve options
            $resolvedOptions = $combinedOptions->all();

            self::validateTypes($resolvedOptions, $defaults->allowedTypes);
            self::validateValues($resolvedOptions, $defaults->allowedValues);

            return $resolvedOptions;
        }

        throw new InvalidArgumentException('The second argument is expected to be given as array, Options instance or OptionsConfig instance.');
    }

    /**
     * Validates that the given option names exist and throws an exception
     * otherwise.
     *
     * @param array        $options         A list of option names and values
     * @param string|array $acceptedOptions The accepted option(s), either passed
     *                                      as single string or in the values of
     *                                      the given array
     * @param bool         $namesAsKeys     If set to true, the option names
     *                                      should be passed in the keys of the
     *                                      accepted options array
     *
     * @throws InvalidOptionsException If any of the options is not present in
     *                                 the accepted options
     *
     * @since 2.6
     */
    public static function validateNames(array $options, $acceptedOptions, $namesAsKeys = false)
    {
        $acceptedOptions = (array) $acceptedOptions;

        if (!$namesAsKeys) {
            $acceptedOptions = array_flip($acceptedOptions);
        }

        $diff = array_diff_key($options, $acceptedOptions);

        if (count($diff) > 0) {
            ksort($acceptedOptions);
            ksort($diff);

            throw new InvalidOptionsException(sprintf(
                (count($diff) > 1 ? 'The options "%s" do not exist.' : 'The option "%s" does not exist.').' Known options are: "%s"',
                implode('", "', array_keys($diff)),
                implode('", "', array_keys($acceptedOptions))
            ));
        }
    }

    /**
     * Validates that the required options are given and throws an exception
     * otherwise.
     *
     * The option names may be any strings that don't consist exclusively of
     * digits. For example, "case1" is a valid option name, "1" is not.
     *
     * @param array        $options         A list of option names and values
     * @param string|array $requiredOptions The required option(s), either
     *                                      passed as single string or in the
     *                                      values of the given array
     * @param bool         $namesAsKeys     If set to true, the option names
     *                                      should be passed in the keys of the
     *                                      required options array
     *
     * @throws MissingOptionsException If a required option is missing
     *
     * @since 2.6
     */
    public static function validateRequired(array $options, $requiredOptions, $namesAsKeys = false)
    {
        $requiredOptions = (array) $requiredOptions;

        if (!$namesAsKeys) {
            $requiredOptions = array_flip($requiredOptions);
        }

        $diff = array_diff_key($requiredOptions, $options);

        if (count($diff) > 0) {
            ksort($diff);

            throw new MissingOptionsException(sprintf(
                count($diff) > 1 ? 'The required options "%s" are missing.' : 'The required option "%s" is missing.',
                implode('", "', array_keys($diff))
            ));
        }
    }

    /**
     * Validates that the given options match the accepted types and
     * throws an exception otherwise.
     *
     * Accepted type names are any types for which a native "is_*()" function
     * exists. For example, "int" is an acceptable type name and will be checked
     * with the "is_int()" function.
     *
     * Types may also be passed as closures which return true or false.
     *
     * @param array $options       A list of option names and values
     * @param array $acceptedTypes A mapping of option names to accepted option
     *                             types. The types may be given as
     *                             string/closure or as array of strings/closures
     *
     * @throws InvalidOptionsException If any of the types does not match the
     *                                 accepted types of the option
     *
     * @since 2.6
     */
    public static function validateTypes(array $options, array $acceptedTypes)
    {
        foreach ($acceptedTypes as $option => $optionTypes) {
            if (!array_key_exists($option, $options)) {
                continue;
            }

            $value = $options[$option];
            $optionTypes = (array) $optionTypes;

            foreach ($optionTypes as $type) {
                $isFunction = 'is_'.$type;

                if (function_exists($isFunction) && $isFunction($value)) {
                    continue 2;
                } elseif ($value instanceof $type) {
                    continue 2;
                }
            }

            throw new InvalidOptionsException(sprintf(
                'The option "%s" with value "%s" is expected to be of type "%s"',
                $option,
                self::formatValue($value),
                self::formatTypesOf($optionTypes)
            ));
        }
    }

    /**
     * Validates that the given option values match the accepted values and
     * throws an exception otherwise.
     *
     * @param array $options        A list of option names and values
     * @param array $acceptedValues A mapping of option names to accepted option
     *                              values. The option values must be given as
     *                              arrays
     *
     * @throws InvalidOptionsException If any of the values does not match the
     *                                 accepted values of the option
     *
     * @since 2.6
     */
    public static function validateValues(array $options, array $acceptedValues)
    {
        foreach ($acceptedValues as $option => $optionValues) {
            if (array_key_exists($option, $options)) {
                if (is_array($optionValues) && !in_array($options[$option], $optionValues, true)) {
                    throw new InvalidOptionsException(sprintf('The option "%s" has the value "%s", but is expected to be one of "%s"', $option, $options[$option], self::formatValues($optionValues)));
                }

                if (is_callable($optionValues) && !call_user_func($optionValues, $options[$option])) {
                    throw new InvalidOptionsException(sprintf('The option "%s" has the value "%s", which it is not valid', $option, $options[$option]));
                }
            }
        }
    }

    /**
     * Returns a string representation of the type of the value.
     *
     * @param mixed $value
     *
     * @return string
     */
    private static function formatTypeOf($value)
    {
        return is_object($value) ? get_class($value) : gettype($value);
    }

    /**
     * Returns a string representation of a list of types.
     *
     * Each of the types is converted to a string using
     * {@link formatTypeOf()}. The values are then concatenated with commas.
     *
     * @param array $types A list of types
     *
     * @return string The string representation of the type list
     *
     * @see formatTypeOf()
     */
    private static function formatTypesOf(array $types)
    {
        foreach ($types as $key => $value) {
            $types[$key] = self::formatTypeOf($value);
        }

        return implode(', ', $types);
    }

    /**
     * Returns a string representation of the value.
     *
     * This method returns the equivalent PHP tokens for most scalar types
     * (i.e. "false" for false, "1" for 1 etc.). Strings are always wrapped
     * in double quotes ("). Objects, arrays and resources are formatted as
     * "object", "array" and "resource". If the parameter $prettyDateTime
     * is set to true, {@link \DateTime} objects will be formatted as
     * RFC-3339 dates ("Y-m-d H:i:s").
     *
     * @param mixed $value The value to format as string
     *
     * @return string The string representation of the passed value
     */
    private static function formatValue($value)
    {
        $isDateTime = $value instanceof \DateTime || $value instanceof \DateTimeInterface;
        if (self::PRETTY_DATE && $isDateTime) {
            if (class_exists('IntlDateFormatter')) {
                $locale = \Locale::getDefault();
                $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
                // neither the native nor the stub IntlDateFormatter support
                // DateTimeImmutable as of yet
                if (!$value instanceof \DateTime) {
                    $value = new \DateTime(
                        $value->format('Y-m-d H:i:s.u e'),
                        $value->getTimezone()
                    );
                }

                return $formatter->format($value);
            }

            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            if (self::OBJECT_TO_STRING && method_exists($value, '__toString')) {
                return $value->__toString();
            }

            return 'object';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_string($value)) {
            return '"'.$value.'"';
        }

        if (is_resource($value)) {
            return 'resource';
        }

        if (null === $value) {
            return 'null';
        }

        if (false === $value) {
            return 'false';
        }

        if (true === $value) {
            return 'true';
        }

        return (string) $value;
    }

    /**
     * Returns a string representation of a list of values.
     *
     * Each of the values is converted to a string using
     * {@link formatValue()}. The values are then concatenated with commas.
     *
     * @param array $values A list of values
     *
     * @return string The string representation of the value list
     *
     * @see formatValue()
     */
    private static function formatValues(array $values)
    {
        foreach ($values as $key => $value) {
            $values[$key] = self::formatValue($value);
        }

        return implode(', ', $values);
    }

    /**
     * Constructs a new object with a set of default options.
     *
     * @param array $options A list of option names and values
     */
    public function __construct(array $options = array())
    {
        foreach ($options as $option => $value) {
            $this->set($option, $value);
        }
    }

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
        if (is_callable($value)) {
            $reflClosure = is_array($value)
                ? new \ReflectionMethod($value[0], $value[1])
                : new \ReflectionFunction($value);
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
            $this->resolveOption($option);
        }

        if (isset($this->normalizers[$option])) {
            $this->normalizeOption($option);
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
                $this->resolveOption($option);
            }
        }

        foreach ($this->normalizers as $option => $normalizer) {
            if (isset($this->normalizers[$option])) {
                $this->normalizeOption($option);
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
    private function resolveOption($option)
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
            $this->options[$option] = call_user_func($closure, $this, $this->options[$option]);
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
    private function normalizeOption($option)
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
