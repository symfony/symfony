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

use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\InvalidArgumentException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Exception\NoSuchOptionException;
use Symfony\Component\OptionsResolver\Exception\OptionDefinitionException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;

/**
 * Validates options and merges them with default values.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class OptionsResolver implements Options
{
    private const VALIDATION_FUNCTIONS = [
        'bool' => 'is_bool',
        'boolean' => 'is_bool',
        'int' => 'is_int',
        'integer' => 'is_int',
        'long' => 'is_int',
        'float' => 'is_float',
        'double' => 'is_float',
        'real' => 'is_float',
        'numeric' => 'is_numeric',
        'string' => 'is_string',
        'scalar' => 'is_scalar',
        'array' => 'is_array',
        'iterable' => 'is_iterable',
        'countable' => 'is_countable',
        'callable' => 'is_callable',
        'object' => 'is_object',
        'resource' => 'is_resource',
    ];

    /**
     * The names of all defined options.
     */
    private array $defined = [];

    /**
     * The default option values.
     */
    private array $defaults = [];

    /**
     * A list of closure for nested options.
     *
     * @var \Closure[][]
     */
    private array $nested = [];

    /**
     * The names of required options.
     */
    private array $required = [];

    /**
     * The resolved option values.
     */
    private array $resolved = [];

    /**
     * A list of normalizer closures.
     *
     * @var \Closure[][]
     */
    private array $normalizers = [];

    /**
     * A list of accepted values for each option.
     */
    private array $allowedValues = [];

    /**
     * A list of accepted types for each option.
     */
    private array $allowedTypes = [];

    /**
     * A list of info messages for each option.
     */
    private array $info = [];

    /**
     * A list of closures for evaluating lazy options.
     */
    private array $lazy = [];

    /**
     * A list of lazy options whose closure is currently being called.
     *
     * This list helps detecting circular dependencies between lazy options.
     */
    private array $calling = [];

    /**
     * A list of deprecated options.
     */
    private array $deprecated = [];

    /**
     * The list of options provided by the user.
     */
    private array $given = [];

    /**
     * Whether the instance is locked for reading.
     *
     * Once locked, the options cannot be changed anymore. This is
     * necessary in order to avoid inconsistencies during the resolving
     * process. If any option is changed after being read, all evaluated
     * lazy options that depend on this option would become invalid.
     */
    private bool $locked = false;

    private array $parentsOptions = [];

    /**
     * Whether the whole options definition is marked as array prototype.
     */
    private ?bool $prototype = null;

    /**
     * The prototype array's index that is being read.
     */
    private int|string|null $prototypeIndex = null;

    /**
     * Whether to ignore undefined options.
     */
    private bool $ignoreUndefined = false;

    /**
     * Sets the default value of a given option.
     *
     * If the default value should be set based on other options, you can pass
     * a closure with the following signature:
     *
     *     function (Options $options) {
     *         // ...
     *     }
     *
     * The closure will be evaluated when {@link resolve()} is called. The
     * closure has access to the resolved values of other options through the
     * passed {@link Options} instance:
     *
     *     function (Options $options) {
     *         if (isset($options['port'])) {
     *             // ...
     *         }
     *     }
     *
     * If you want to access the previously set default value, add a second
     * argument to the closure's signature:
     *
     *     $options->setDefault('name', 'Default Name');
     *
     *     $options->setDefault('name', function (Options $options, $previousValue) {
     *         // 'Default Name' === $previousValue
     *     });
     *
     * This is mostly useful if the configuration of the {@link Options} object
     * is spread across different locations of your code, such as base and
     * sub-classes.
     *
     * If you want to define nested options, you can pass a closure with the
     * following signature:
     *
     *     $options->setDefault('database', function (OptionsResolver $resolver) {
     *         $resolver->setDefined(['dbname', 'host', 'port', 'user', 'pass']);
     *     }
     *
     * To get access to the parent options, add a second argument to the closure's
     * signature:
     *
     *     function (OptionsResolver $resolver, Options $parent) {
     *         // 'default' === $parent['connection']
     *     }
     *
     * @return $this
     *
     * @throws AccessException If called from a lazy option or normalizer
     */
    public function setDefault(string $option, mixed $value): static
    {
        // Setting is not possible once resolving starts, because then lazy
        // options could manipulate the state of the object, leading to
        // inconsistent results.
        if ($this->locked) {
            throw new AccessException('Default values cannot be set from a lazy option or normalizer.');
        }

        // If an option is a closure that should be evaluated lazily, store it
        // in the "lazy" property.
        if ($value instanceof \Closure) {
            $reflClosure = new \ReflectionFunction($value);
            $params = $reflClosure->getParameters();

            if (isset($params[0]) && Options::class === $this->getParameterClassName($params[0])) {
                // Initialize the option if no previous value exists
                if (!isset($this->defaults[$option])) {
                    $this->defaults[$option] = null;
                }

                // Ignore previous lazy options if the closure has no second parameter
                if (!isset($this->lazy[$option]) || !isset($params[1])) {
                    $this->lazy[$option] = [];
                }

                // Store closure for later evaluation
                $this->lazy[$option][] = $value;
                $this->defined[$option] = true;

                // Make sure the option is processed and is not nested anymore
                unset($this->resolved[$option], $this->nested[$option]);

                return $this;
            }

            if (isset($params[0]) && null !== ($type = $params[0]->getType()) && self::class === $type->getName() && (!isset($params[1]) || (($type = $params[1]->getType()) instanceof \ReflectionNamedType && Options::class === $type->getName()))) {
                // Store closure for later evaluation
                $this->nested[$option][] = $value;
                $this->defaults[$option] = [];
                $this->defined[$option] = true;

                // Make sure the option is processed and is not lazy anymore
                unset($this->resolved[$option], $this->lazy[$option]);

                return $this;
            }
        }

        // This option is not lazy nor nested anymore
        unset($this->lazy[$option], $this->nested[$option]);

        // Yet undefined options can be marked as resolved, because we only need
        // to resolve options with lazy closures, normalizers or validation
        // rules, none of which can exist for undefined options
        // If the option was resolved before, update the resolved value
        if (!isset($this->defined[$option]) || \array_key_exists($option, $this->resolved)) {
            $this->resolved[$option] = $value;
        }

        $this->defaults[$option] = $value;
        $this->defined[$option] = true;

        return $this;
    }

    /**
     * @return $this
     *
     * @throws AccessException If called from a lazy option or normalizer
     */
    public function setDefaults(array $defaults): static
    {
        foreach ($defaults as $option => $value) {
            $this->setDefault($option, $value);
        }

        return $this;
    }

    /**
     * Returns whether a default value is set for an option.
     *
     * Returns true if {@link setDefault()} was called for this option.
     * An option is also considered set if it was set to null.
     */
    public function hasDefault(string $option): bool
    {
        return \array_key_exists($option, $this->defaults);
    }

    /**
     * Marks one or more options as required.
     *
     * @param string|string[] $optionNames One or more option names
     *
     * @return $this
     *
     * @throws AccessException If called from a lazy option or normalizer
     */
    public function setRequired(string|array $optionNames): static
    {
        if ($this->locked) {
            throw new AccessException('Options cannot be made required from a lazy option or normalizer.');
        }

        foreach ((array) $optionNames as $option) {
            $this->defined[$option] = true;
            $this->required[$option] = true;
        }

        return $this;
    }

    /**
     * Returns whether an option is required.
     *
     * An option is required if it was passed to {@link setRequired()}.
     */
    public function isRequired(string $option): bool
    {
        return isset($this->required[$option]);
    }

    /**
     * Returns the names of all required options.
     *
     * @return string[]
     *
     * @see isRequired()
     */
    public function getRequiredOptions(): array
    {
        return array_keys($this->required);
    }

    /**
     * Returns whether an option is missing a default value.
     *
     * An option is missing if it was passed to {@link setRequired()}, but not
     * to {@link setDefault()}. This option must be passed explicitly to
     * {@link resolve()}, otherwise an exception will be thrown.
     */
    public function isMissing(string $option): bool
    {
        return isset($this->required[$option]) && !\array_key_exists($option, $this->defaults);
    }

    /**
     * Returns the names of all options missing a default value.
     *
     * @return string[]
     */
    public function getMissingOptions(): array
    {
        return array_keys(array_diff_key($this->required, $this->defaults));
    }

    /**
     * Defines a valid option name.
     *
     * Defines an option name without setting a default value. The option will
     * be accepted when passed to {@link resolve()}. When not passed, the
     * option will not be included in the resolved options.
     *
     * @param string|string[] $optionNames One or more option names
     *
     * @return $this
     *
     * @throws AccessException If called from a lazy option or normalizer
     */
    public function setDefined(string|array $optionNames): static
    {
        if ($this->locked) {
            throw new AccessException('Options cannot be defined from a lazy option or normalizer.');
        }

        foreach ((array) $optionNames as $option) {
            $this->defined[$option] = true;
        }

        return $this;
    }

    /**
     * Returns whether an option is defined.
     *
     * Returns true for any option passed to {@link setDefault()},
     * {@link setRequired()} or {@link setDefined()}.
     */
    public function isDefined(string $option): bool
    {
        return isset($this->defined[$option]);
    }

    /**
     * Returns the names of all defined options.
     *
     * @return string[]
     *
     * @see isDefined()
     */
    public function getDefinedOptions(): array
    {
        return array_keys($this->defined);
    }

    public function isNested(string $option): bool
    {
        return isset($this->nested[$option]);
    }

    /**
     * Deprecates an option, allowed types or values.
     *
     * Instead of passing the message, you may also pass a closure with the
     * following signature:
     *
     *     function (Options $options, $value): string {
     *         // ...
     *     }
     *
     * The closure receives the value as argument and should return a string.
     * Return an empty string to ignore the option deprecation.
     *
     * The closure is invoked when {@link resolve()} is called. The parameter
     * passed to the closure is the value of the option after validating it
     * and before normalizing it.
     *
     * @param string          $package The name of the composer package that is triggering the deprecation
     * @param string          $version The version of the package that introduced the deprecation
     * @param string|\Closure $message The deprecation message to use
     *
     * @return $this
     */
    public function setDeprecated(string $option, string $package, string $version, string|\Closure $message = 'The option "%name%" is deprecated.'): static
    {
        if ($this->locked) {
            throw new AccessException('Options cannot be deprecated from a lazy option or normalizer.');
        }

        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist, defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        if (!\is_string($message) && !$message instanceof \Closure) {
            throw new InvalidArgumentException(sprintf('Invalid type for deprecation message argument, expected string or \Closure, but got "%s".', get_debug_type($message)));
        }

        // ignore if empty string
        if ('' === $message) {
            return $this;
        }

        $this->deprecated[$option] = [
            'package' => $package,
            'version' => $version,
            'message' => $message,
        ];

        // Make sure the option is processed
        unset($this->resolved[$option]);

        return $this;
    }

    public function isDeprecated(string $option): bool
    {
        return isset($this->deprecated[$option]);
    }

    /**
     * Sets the normalizer for an option.
     *
     * The normalizer should be a closure with the following signature:
     *
     *     function (Options $options, $value) {
     *         // ...
     *     }
     *
     * The closure is invoked when {@link resolve()} is called. The closure
     * has access to the resolved values of other options through the passed
     * {@link Options} instance.
     *
     * The second parameter passed to the closure is the value of
     * the option.
     *
     * The resolved option value is set to the return value of the closure.
     *
     * @return $this
     *
     * @throws UndefinedOptionsException If the option is undefined
     * @throws AccessException           If called from a lazy option or normalizer
     */
    public function setNormalizer(string $option, \Closure $normalizer)
    {
        if ($this->locked) {
            throw new AccessException('Normalizers cannot be set from a lazy option or normalizer.');
        }

        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        $this->normalizers[$option] = [$normalizer];

        // Make sure the option is processed
        unset($this->resolved[$option]);

        return $this;
    }

    /**
     * Adds a normalizer for an option.
     *
     * The normalizer should be a closure with the following signature:
     *
     *     function (Options $options, $value): mixed {
     *         // ...
     *     }
     *
     * The closure is invoked when {@link resolve()} is called. The closure
     * has access to the resolved values of other options through the passed
     * {@link Options} instance.
     *
     * The second parameter passed to the closure is the value of
     * the option.
     *
     * The resolved option value is set to the return value of the closure.
     *
     * @return $this
     *
     * @throws UndefinedOptionsException If the option is undefined
     * @throws AccessException           If called from a lazy option or normalizer
     */
    public function addNormalizer(string $option, \Closure $normalizer, bool $forcePrepend = false): static
    {
        if ($this->locked) {
            throw new AccessException('Normalizers cannot be set from a lazy option or normalizer.');
        }

        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        if ($forcePrepend) {
            $this->normalizers[$option] ??= [];
            array_unshift($this->normalizers[$option], $normalizer);
        } else {
            $this->normalizers[$option][] = $normalizer;
        }

        // Make sure the option is processed
        unset($this->resolved[$option]);

        return $this;
    }

    /**
     * Sets allowed values for an option.
     *
     * Instead of passing values, you may also pass a closures with the
     * following signature:
     *
     *     function ($value) {
     *         // return true or false
     *     }
     *
     * The closure receives the value as argument and should return true to
     * accept the value and false to reject the value.
     *
     * @param mixed $allowedValues One or more acceptable values/closures
     *
     * @return $this
     *
     * @throws UndefinedOptionsException If the option is undefined
     * @throws AccessException           If called from a lazy option or normalizer
     */
    public function setAllowedValues(string $option, mixed $allowedValues)
    {
        if ($this->locked) {
            throw new AccessException('Allowed values cannot be set from a lazy option or normalizer.');
        }

        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        $this->allowedValues[$option] = \is_array($allowedValues) ? $allowedValues : [$allowedValues];

        // Make sure the option is processed
        unset($this->resolved[$option]);

        return $this;
    }

    /**
     * Adds allowed values for an option.
     *
     * The values are merged with the allowed values defined previously.
     *
     * Instead of passing values, you may also pass a closures with the
     * following signature:
     *
     *     function ($value) {
     *         // return true or false
     *     }
     *
     * The closure receives the value as argument and should return true to
     * accept the value and false to reject the value.
     *
     * @param mixed $allowedValues One or more acceptable values/closures
     *
     * @return $this
     *
     * @throws UndefinedOptionsException If the option is undefined
     * @throws AccessException           If called from a lazy option or normalizer
     */
    public function addAllowedValues(string $option, mixed $allowedValues)
    {
        if ($this->locked) {
            throw new AccessException('Allowed values cannot be added from a lazy option or normalizer.');
        }

        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        if (!\is_array($allowedValues)) {
            $allowedValues = [$allowedValues];
        }

        if (!isset($this->allowedValues[$option])) {
            $this->allowedValues[$option] = $allowedValues;
        } else {
            $this->allowedValues[$option] = array_merge($this->allowedValues[$option], $allowedValues);
        }

        // Make sure the option is processed
        unset($this->resolved[$option]);

        return $this;
    }

    /**
     * Sets allowed types for an option.
     *
     * Any type for which a corresponding is_<type>() function exists is
     * acceptable. Additionally, fully-qualified class or interface names may
     * be passed.
     *
     * @param string|string[] $allowedTypes One or more accepted types
     *
     * @return $this
     *
     * @throws UndefinedOptionsException If the option is undefined
     * @throws AccessException           If called from a lazy option or normalizer
     */
    public function setAllowedTypes(string $option, string|array $allowedTypes)
    {
        if ($this->locked) {
            throw new AccessException('Allowed types cannot be set from a lazy option or normalizer.');
        }

        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        $this->allowedTypes[$option] = (array) $allowedTypes;

        // Make sure the option is processed
        unset($this->resolved[$option]);

        return $this;
    }

    /**
     * Adds allowed types for an option.
     *
     * The types are merged with the allowed types defined previously.
     *
     * Any type for which a corresponding is_<type>() function exists is
     * acceptable. Additionally, fully-qualified class or interface names may
     * be passed.
     *
     * @param string|string[] $allowedTypes One or more accepted types
     *
     * @return $this
     *
     * @throws UndefinedOptionsException If the option is undefined
     * @throws AccessException           If called from a lazy option or normalizer
     */
    public function addAllowedTypes(string $option, string|array $allowedTypes)
    {
        if ($this->locked) {
            throw new AccessException('Allowed types cannot be added from a lazy option or normalizer.');
        }

        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        if (!isset($this->allowedTypes[$option])) {
            $this->allowedTypes[$option] = (array) $allowedTypes;
        } else {
            $this->allowedTypes[$option] = array_merge($this->allowedTypes[$option], (array) $allowedTypes);
        }

        // Make sure the option is processed
        unset($this->resolved[$option]);

        return $this;
    }

    /**
     * Defines an option configurator with the given name.
     */
    public function define(string $option): OptionConfigurator
    {
        if (isset($this->defined[$option])) {
            throw new OptionDefinitionException(sprintf('The option "%s" is already defined.', $option));
        }

        return new OptionConfigurator($option, $this);
    }

    /**
     * Sets an info message for an option.
     *
     * @return $this
     *
     * @throws UndefinedOptionsException If the option is undefined
     * @throws AccessException           If called from a lazy option or normalizer
     */
    public function setInfo(string $option, string $info): static
    {
        if ($this->locked) {
            throw new AccessException('The Info message cannot be set from a lazy option or normalizer.');
        }

        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        $this->info[$option] = $info;

        return $this;
    }

    /**
     * Gets the info message for an option.
     */
    public function getInfo(string $option): ?string
    {
        if (!isset($this->defined[$option])) {
            throw new UndefinedOptionsException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
        }

        return $this->info[$option] ?? null;
    }

    /**
     * Marks the whole options definition as array prototype.
     *
     * @return $this
     *
     * @throws AccessException If called from a lazy option, a normalizer or a root definition
     */
    public function setPrototype(bool $prototype): static
    {
        if ($this->locked) {
            throw new AccessException('The prototype property cannot be set from a lazy option or normalizer.');
        }

        if (null === $this->prototype && $prototype) {
            throw new AccessException('The prototype property cannot be set from a root definition.');
        }

        $this->prototype = $prototype;

        return $this;
    }

    public function isPrototype(): bool
    {
        return $this->prototype ?? false;
    }

    /**
     * Removes the option with the given name.
     *
     * Undefined options are ignored.
     *
     * @param string|string[] $optionNames One or more option names
     *
     * @return $this
     *
     * @throws AccessException If called from a lazy option or normalizer
     */
    public function remove(string|array $optionNames): static
    {
        if ($this->locked) {
            throw new AccessException('Options cannot be removed from a lazy option or normalizer.');
        }

        foreach ((array) $optionNames as $option) {
            unset($this->defined[$option], $this->defaults[$option], $this->required[$option], $this->resolved[$option]);
            unset($this->lazy[$option], $this->normalizers[$option], $this->allowedTypes[$option], $this->allowedValues[$option], $this->info[$option]);
        }

        return $this;
    }

    /**
     * Removes all options.
     *
     * @return $this
     *
     * @throws AccessException If called from a lazy option or normalizer
     */
    public function clear(): static
    {
        if ($this->locked) {
            throw new AccessException('Options cannot be cleared from a lazy option or normalizer.');
        }

        $this->defined = [];
        $this->defaults = [];
        $this->nested = [];
        $this->required = [];
        $this->resolved = [];
        $this->lazy = [];
        $this->normalizers = [];
        $this->allowedTypes = [];
        $this->allowedValues = [];
        $this->deprecated = [];
        $this->info = [];

        return $this;
    }

    /**
     * Merges options with the default values stored in the container and
     * validates them.
     *
     * Exceptions are thrown if:
     *
     *  - Undefined options are passed;
     *  - Required options are missing;
     *  - Options have invalid types;
     *  - Options have invalid values.
     *
     * @throws UndefinedOptionsException If an option name is undefined
     * @throws InvalidOptionsException   If an option doesn't fulfill the
     *                                   specified validation rules
     * @throws MissingOptionsException   If a required option is missing
     * @throws OptionDefinitionException If there is a cyclic dependency between
     *                                   lazy options and/or normalizers
     * @throws NoSuchOptionException     If a lazy option reads an unavailable option
     * @throws AccessException           If called from a lazy option or normalizer
     */
    public function resolve(array $options = []): array
    {
        if ($this->locked) {
            throw new AccessException('Options cannot be resolved from a lazy option or normalizer.');
        }

        // Allow this method to be called multiple times
        $clone = clone $this;

        // Make sure that no unknown options are passed
        $diff = $this->ignoreUndefined ? [] : array_diff_key($options, $clone->defined);

        if (\count($diff) > 0) {
            ksort($clone->defined);
            ksort($diff);

            throw new UndefinedOptionsException(sprintf((\count($diff) > 1 ? 'The options "%s" do not exist.' : 'The option "%s" does not exist.').' Defined options are: "%s".', $this->formatOptions(array_keys($diff)), implode('", "', array_keys($clone->defined))));
        }

        // Override options set by the user
        foreach ($options as $option => $value) {
            if ($this->ignoreUndefined && !isset($clone->defined[$option])) {
                continue;
            }

            $clone->given[$option] = true;
            $clone->defaults[$option] = $value;
            unset($clone->resolved[$option], $clone->lazy[$option]);
        }

        // Check whether any required option is missing
        $diff = array_diff_key($clone->required, $clone->defaults);

        if (\count($diff) > 0) {
            ksort($diff);

            throw new MissingOptionsException(sprintf(\count($diff) > 1 ? 'The required options "%s" are missing.' : 'The required option "%s" is missing.', $this->formatOptions(array_keys($diff))));
        }

        // Lock the container
        $clone->locked = true;

        // Now process the individual options. Use offsetGet(), which resolves
        // the option itself and any options that the option depends on
        foreach ($clone->defaults as $option => $_) {
            $clone->offsetGet($option);
        }

        return $clone->resolved;
    }

    /**
     * Returns the resolved value of an option.
     *
     * @param bool $triggerDeprecation Whether to trigger the deprecation or not (true by default)
     *
     * @throws AccessException           If accessing this method outside of
     *                                   {@link resolve()}
     * @throws NoSuchOptionException     If the option is not set
     * @throws InvalidOptionsException   If the option doesn't fulfill the
     *                                   specified validation rules
     * @throws OptionDefinitionException If there is a cyclic dependency between
     *                                   lazy options and/or normalizers
     */
    public function offsetGet(mixed $option, bool $triggerDeprecation = true): mixed
    {
        if (!$this->locked) {
            throw new AccessException('Array access is only supported within closures of lazy options and normalizers.');
        }

        // Shortcut for resolved options
        if (isset($this->resolved[$option]) || \array_key_exists($option, $this->resolved)) {
            if ($triggerDeprecation && isset($this->deprecated[$option]) && (isset($this->given[$option]) || $this->calling) && \is_string($this->deprecated[$option]['message'])) {
                trigger_deprecation($this->deprecated[$option]['package'], $this->deprecated[$option]['version'], strtr($this->deprecated[$option]['message'], ['%name%' => $option]));
            }

            return $this->resolved[$option];
        }

        // Check whether the option is set at all
        if (!isset($this->defaults[$option]) && !\array_key_exists($option, $this->defaults)) {
            if (!isset($this->defined[$option])) {
                throw new NoSuchOptionException(sprintf('The option "%s" does not exist. Defined options are: "%s".', $this->formatOptions([$option]), implode('", "', array_keys($this->defined))));
            }

            throw new NoSuchOptionException(sprintf('The optional option "%s" has no value set. You should make sure it is set with "isset" before reading it.', $this->formatOptions([$option])));
        }

        $value = $this->defaults[$option];

        // Resolve the option if it is a nested definition
        if (isset($this->nested[$option])) {
            // If the closure is already being called, we have a cyclic dependency
            if (isset($this->calling[$option])) {
                throw new OptionDefinitionException(sprintf('The options "%s" have a cyclic dependency.', $this->formatOptions(array_keys($this->calling))));
            }

            if (!\is_array($value)) {
                throw new InvalidOptionsException(sprintf('The nested option "%s" with value %s is expected to be of type array, but is of type "%s".', $this->formatOptions([$option]), $this->formatValue($value), get_debug_type($value)));
            }

            // The following section must be protected from cyclic calls.
            $this->calling[$option] = true;
            try {
                $resolver = new self();
                $resolver->prototype = false;
                $resolver->parentsOptions = $this->parentsOptions;
                $resolver->parentsOptions[] = $option;
                foreach ($this->nested[$option] as $closure) {
                    $closure($resolver, $this);
                }

                if ($resolver->prototype) {
                    $values = [];
                    foreach ($value as $index => $prototypeValue) {
                        if (!\is_array($prototypeValue)) {
                            throw new InvalidOptionsException(sprintf('The value of the option "%s" is expected to be of type array of array, but is of type array of "%s".', $this->formatOptions([$option]), get_debug_type($prototypeValue)));
                        }

                        $resolver->prototypeIndex = $index;
                        $values[$index] = $resolver->resolve($prototypeValue);
                    }
                    $value = $values;
                } else {
                    $value = $resolver->resolve($value);
                }
            } finally {
                $resolver->prototypeIndex = null;
                unset($this->calling[$option]);
            }
        }

        // Resolve the option if the default value is lazily evaluated
        if (isset($this->lazy[$option])) {
            // If the closure is already being called, we have a cyclic
            // dependency
            if (isset($this->calling[$option])) {
                throw new OptionDefinitionException(sprintf('The options "%s" have a cyclic dependency.', $this->formatOptions(array_keys($this->calling))));
            }

            // The following section must be protected from cyclic
            // calls. Set $calling for the current $option to detect a cyclic
            // dependency
            // BEGIN
            $this->calling[$option] = true;
            try {
                foreach ($this->lazy[$option] as $closure) {
                    $value = $closure($this, $value);
                }
            } finally {
                unset($this->calling[$option]);
            }
            // END
        }

        // Validate the type of the resolved option
        if (isset($this->allowedTypes[$option])) {
            $valid = true;
            $invalidTypes = [];

            foreach ($this->allowedTypes[$option] as $type) {
                if ($valid = $this->verifyTypes($type, $value, $invalidTypes)) {
                    break;
                }
            }

            if (!$valid) {
                $fmtActualValue = $this->formatValue($value);
                $fmtAllowedTypes = implode('" or "', $this->allowedTypes[$option]);
                $fmtProvidedTypes = implode('|', array_keys($invalidTypes));
                $allowedContainsArrayType = \count(array_filter($this->allowedTypes[$option], static fn ($item) => str_ends_with($item, '[]'))) > 0;

                if (\is_array($value) && $allowedContainsArrayType) {
                    throw new InvalidOptionsException(sprintf('The option "%s" with value %s is expected to be of type "%s", but one of the elements is of type "%s".', $this->formatOptions([$option]), $fmtActualValue, $fmtAllowedTypes, $fmtProvidedTypes));
                }

                throw new InvalidOptionsException(sprintf('The option "%s" with value %s is expected to be of type "%s", but is of type "%s".', $this->formatOptions([$option]), $fmtActualValue, $fmtAllowedTypes, $fmtProvidedTypes));
            }
        }

        // Validate the value of the resolved option
        if (isset($this->allowedValues[$option])) {
            $success = false;
            $printableAllowedValues = [];

            foreach ($this->allowedValues[$option] as $allowedValue) {
                if ($allowedValue instanceof \Closure) {
                    if ($allowedValue($value)) {
                        $success = true;
                        break;
                    }

                    // Don't include closures in the exception message
                    continue;
                }

                if ($value === $allowedValue) {
                    $success = true;
                    break;
                }

                $printableAllowedValues[] = $allowedValue;
            }

            if (!$success) {
                $message = sprintf(
                    'The option "%s" with value %s is invalid.',
                    $this->formatOptions([$option]),
                    $this->formatValue($value)
                );

                if (\count($printableAllowedValues) > 0) {
                    $message .= sprintf(
                        ' Accepted values are: %s.',
                        $this->formatValues($printableAllowedValues)
                    );
                }

                if (isset($this->info[$option])) {
                    $message .= sprintf(' Info: %s.', $this->info[$option]);
                }

                throw new InvalidOptionsException($message);
            }
        }

        // Check whether the option is deprecated
        // and it is provided by the user or is being called from a lazy evaluation
        if ($triggerDeprecation && isset($this->deprecated[$option]) && (isset($this->given[$option]) || ($this->calling && \is_string($this->deprecated[$option]['message'])))) {
            $deprecation = $this->deprecated[$option];
            $message = $this->deprecated[$option]['message'];

            if ($message instanceof \Closure) {
                // If the closure is already being called, we have a cyclic dependency
                if (isset($this->calling[$option])) {
                    throw new OptionDefinitionException(sprintf('The options "%s" have a cyclic dependency.', $this->formatOptions(array_keys($this->calling))));
                }

                $this->calling[$option] = true;
                try {
                    if (!\is_string($message = $message($this, $value))) {
                        throw new InvalidOptionsException(sprintf('Invalid type for deprecation message, expected string but got "%s", return an empty string to ignore.', get_debug_type($message)));
                    }
                } finally {
                    unset($this->calling[$option]);
                }
            }

            if ('' !== $message) {
                trigger_deprecation($deprecation['package'], $deprecation['version'], strtr($message, ['%name%' => $option]));
            }
        }

        // Normalize the validated option
        if (isset($this->normalizers[$option])) {
            // If the closure is already being called, we have a cyclic
            // dependency
            if (isset($this->calling[$option])) {
                throw new OptionDefinitionException(sprintf('The options "%s" have a cyclic dependency.', $this->formatOptions(array_keys($this->calling))));
            }

            // The following section must be protected from cyclic
            // calls. Set $calling for the current $option to detect a cyclic
            // dependency
            // BEGIN
            $this->calling[$option] = true;
            try {
                foreach ($this->normalizers[$option] as $normalizer) {
                    $value = $normalizer($this, $value);
                }
            } finally {
                unset($this->calling[$option]);
            }
            // END
        }

        // Mark as resolved
        $this->resolved[$option] = $value;

        return $value;
    }

    private function verifyTypes(string $type, mixed $value, array &$invalidTypes, int $level = 0): bool
    {
        if (\is_array($value) && str_ends_with($type, '[]')) {
            $type = substr($type, 0, -2);
            $valid = true;

            foreach ($value as $val) {
                if (!$this->verifyTypes($type, $val, $invalidTypes, $level + 1)) {
                    $valid = false;
                }
            }

            return $valid;
        }

        if (('null' === $type && null === $value) || (isset(self::VALIDATION_FUNCTIONS[$type]) ? self::VALIDATION_FUNCTIONS[$type]($value) : $value instanceof $type)) {
            return true;
        }

        if (!$invalidTypes || $level > 0) {
            $invalidTypes[get_debug_type($value)] = true;
        }

        return false;
    }

    /**
     * Returns whether a resolved option with the given name exists.
     *
     * @throws AccessException If accessing this method outside of {@link resolve()}
     *
     * @see \ArrayAccess::offsetExists()
     */
    public function offsetExists(mixed $option): bool
    {
        if (!$this->locked) {
            throw new AccessException('Array access is only supported within closures of lazy options and normalizers.');
        }

        return \array_key_exists($option, $this->defaults);
    }

    /**
     * Not supported.
     *
     * @throws AccessException
     */
    public function offsetSet(mixed $option, mixed $value): void
    {
        throw new AccessException('Setting options via array access is not supported. Use setDefault() instead.');
    }

    /**
     * Not supported.
     *
     * @throws AccessException
     */
    public function offsetUnset(mixed $option): void
    {
        throw new AccessException('Removing options via array access is not supported. Use remove() instead.');
    }

    /**
     * Returns the number of set options.
     *
     * This may be only a subset of the defined options.
     *
     * @throws AccessException If accessing this method outside of {@link resolve()}
     *
     * @see \Countable::count()
     */
    public function count(): int
    {
        if (!$this->locked) {
            throw new AccessException('Counting is only supported within closures of lazy options and normalizers.');
        }

        return \count($this->defaults);
    }

    /**
     * Sets whether ignore undefined options.
     *
     * @return $this
     */
    public function setIgnoreUndefined(bool $ignore = true): static
    {
        $this->ignoreUndefined = $ignore;

        return $this;
    }

    /**
     * Returns a string representation of the value.
     *
     * This method returns the equivalent PHP tokens for most scalar types
     * (i.e. "false" for false, "1" for 1 etc.). Strings are always wrapped
     * in double quotes (").
     */
    private function formatValue(mixed $value): string
    {
        if (\is_object($value)) {
            return $value::class;
        }

        if (\is_array($value)) {
            return 'array';
        }

        if (\is_string($value)) {
            return '"'.$value.'"';
        }

        if (\is_resource($value)) {
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
     * @see formatValue()
     */
    private function formatValues(array $values): string
    {
        foreach ($values as $key => $value) {
            $values[$key] = $this->formatValue($value);
        }

        return implode(', ', $values);
    }

    private function formatOptions(array $options): string
    {
        if ($this->parentsOptions) {
            $prefix = array_shift($this->parentsOptions);
            if ($this->parentsOptions) {
                $prefix .= sprintf('[%s]', implode('][', $this->parentsOptions));
            }

            if ($this->prototype && null !== $this->prototypeIndex) {
                $prefix .= sprintf('[%s]', $this->prototypeIndex);
            }

            $options = array_map(static fn (string $option): string => sprintf('%s[%s]', $prefix, $option), $options);
        }

        return implode('", "', $options);
    }

    private function getParameterClassName(\ReflectionParameter $parameter): ?string
    {
        if (!($type = $parameter->getType()) instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }
}
