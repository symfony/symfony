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
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

/**
 * Helper for merging default and concrete option values.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class OptionsResolver
{
    /**
     * The default option values.
     * @var Options
     */
    private $defaultOptions;

    /**
     * The options known by the resolver.
     * @var array
     */
    private $knownOptions = array();

    /**
     * The options required to be passed to resolve().
     * @var array
     */
    private $requiredOptions = array();

    /**
     * A list of accepted values for each option.
     * @var array
     */
    private $allowedValues = array();

    /**
     * Creates a new instance.
     */
    public function __construct()
    {
        $this->defaultOptions = new Options();
    }

    /**
     * Sets default option values.
     *
     * @param array $defaultValues A list of option names as keys and default values
     *                             as values. The option values may be closures
     *                             of the following signatures:
     *
     *                                 - function (Options $options)
     *                                 - function (Options $options, $previousValue)
     *
     * @return OptionsResolver The resolver instance.
     */
    public function setDefaults(array $defaultValues)
    {
        foreach ($defaultValues as $option => $value) {
            $this->defaultOptions->overload($option, $value);
            $this->knownOptions[$option] = true;
        }

        return $this;
    }

    /**
     * Replaces default option values.
     *
     * Old defaults are erased, which means that closures passed here can't
     * access the previous default value. This may be useful to improve
     * performance if the previous default value is calculated by an expensive
     * closure.
     *
     * @param array $defaultValues A list of option names as keys and default values
     *                             as values. The option values may be closures
     *                             of the following signature:
     *
     *                                 - function (Options $options)
     *
     * @return OptionsResolver The resolver instance.
     */
    public function replaceDefaults(array $defaultValues)
    {
        foreach ($defaultValues as $option => $value) {
            $this->defaultOptions->set($option, $value);
            $this->knownOptions[$option] = true;
        }

        return $this;
    }

    /**
     * Sets optional options.
     *
     * This method is identical to `setDefaults`, only that no default values
     * are configured for the options. If these options are not passed to
     * resolve(), they will be missing in the final options array. This can be
     * helpful if you want to determine whether an option has been set or not.
     *
     * @param array $optionNames A list of option names.
     *
     * @return OptionsResolver The resolver instance.
     *
     * @throws OptionDefinitionException  When trying to pass default values.
     */
    public function setOptional(array $optionNames)
    {
        foreach ($optionNames as $key => $option) {
            if (!is_int($key)) {
                throw new OptionDefinitionException('You should not pass default values to setOptional()');
            }

            $this->knownOptions[$option] = true;
        }

        return $this;
    }

    /**
     * Sets required options.
     *
     * If these options are not passed to resolve(), an exception will be thrown.
     *
     * @param array $optionNames A list of option names.
     *
     * @return OptionsResolver The resolver instance.
     *
     * @throws OptionDefinitionException  When trying to pass default values.
     */
    public function setRequired(array $optionNames)
    {
        foreach ($optionNames as $key => $option) {
            if (!is_int($key)) {
                throw new OptionDefinitionException('You should not pass default values to setRequired()');
            }

            $this->knownOptions[$option] = true;
            $this->requiredOptions[$option] = true;
        }

        return $this;
    }

    /**
     * Sets allowed values for a list of options.
     *
     * @param array $allowedValues A list of option names as keys and arrays
     *                             with values acceptable for that option as
     *                             values.
     *
     * @return OptionsResolver The resolver instance.
     *
     * @throws InvalidOptionsException If an option has not been defined for
     *                                 which an allowed value is set.
     */
    public function setAllowedValues(array $allowedValues)
    {
        $this->validateOptionNames(array_keys($allowedValues));

        $this->allowedValues = array_replace($this->allowedValues, $allowedValues);

        return $this;
    }

    /**
     * Adds allowed values for a list of options.
     *
     * The values are merged with the allowed values defined previously.
     *
     * @param array $allowedValues A list of option names as keys and arrays
     *                             with values acceptable for that option as
     *                             values.
     *
     * @return OptionsResolver The resolver instance.
     *
     * @throws InvalidOptionsException If an option has not been defined for
     *                                 which an allowed value is set.
     */
    public function addAllowedValues(array $allowedValues)
    {
        $this->validateOptionNames(array_keys($allowedValues));

        $this->allowedValues = array_merge_recursive($this->allowedValues, $allowedValues);

        return $this;
    }

    /**
     * Returns whether an option is known.
     *
     * An option is known if it has been passed to either {@link setDefaults()},
     * {@link setRequired()} or {@link setOptional()} before.
     *
     * @param string $option The name of the option.
     * @return Boolean        Whether the option is known.
     */
    public function isKnown($option)
    {
        return isset($this->knownOptions[$option]);
    }

    /**
     * Returns whether an option is required.
     *
     * An option is required if it has been passed to {@link setRequired()},
     * but not to {@link setDefaults()}. That is, the option has been declared
     * as required and no default value has been set.
     *
     * @param string $option The name of the option.
     * @return Boolean        Whether the option is required.
     */
    public function isRequired($option)
    {
        return isset($this->requiredOptions[$option]) && !isset($this->defaultOptions[$option]);
    }

    /**
     * Returns the combination of the default and the passed options.
     *
     * @param array $options The custom option values.
     *
     * @return array A list of options and their values.
     *
     * @throws InvalidOptionsException   If any of the passed options has not
     *                                   been defined or does not contain an
     *                                   allowed value.
     * @throws MissingOptionsException   If a required option is missing.
     * @throws OptionDefinitionException If a cyclic dependency is detected
     *                                   between two lazy options.
     */
    public function resolve(array $options)
    {
        $this->validateOptionNames(array_keys($options));

        // Make sure this method can be called multiple times
        $combinedOptions = clone $this->defaultOptions;

        // Override options set by the user
        foreach ($options as $option => $value) {
            $combinedOptions->set($option, $value);
        }

        // Resolve options
        $resolvedOptions = $combinedOptions->all();

        // Validate against allowed values
        $this->validateOptionValues($resolvedOptions);

        return $resolvedOptions;
    }

    /**
     * Validates that the given option names exist and throws an exception
     * otherwise.
     *
     * @param array $optionNames A list of option names.
     *
     * @throws InvalidOptionsException If any of the options has not been
     *                                 defined.
     * @throws MissingOptionsException If a required option is missing.
     */
    private function validateOptionNames(array $optionNames)
    {
        ksort($this->knownOptions);

        $knownOptions = array_keys($this->knownOptions);
        $diff = array_diff($optionNames, $knownOptions);

        sort($diff);

        if (count($diff) > 0) {
            if (count($diff) > 1) {
                throw new InvalidOptionsException(sprintf('The options "%s" do not exist. Known options are: "%s"', implode('", "', $diff), implode('", "', $knownOptions)));
            }

            throw new InvalidOptionsException(sprintf('The option "%s" does not exist. Known options are: "%s"', current($diff), implode('", "', $knownOptions)));
        }

        ksort($this->requiredOptions);

        $requiredOptions = array_keys($this->requiredOptions);
        $diff = array_diff($requiredOptions, $optionNames);

        sort($diff);

        if (count($diff) > 0) {
            if (count($diff) > 1) {
                throw new MissingOptionsException(sprintf('The required options "%s" are missing.',
                    implode('",
                "', $diff)));
            }

            throw new MissingOptionsException(sprintf('The required option "%s" is  missing.', current($diff)));
        }
    }

    /**
     * Validates that the given option values match the allowed values and
     * throws an exception otherwise.
     *
     * @param array $options A list of option values.
     *
     * @throws InvalidOptionsException  If any of the values does not match the
     *                                  allowed values of the option.
     */
    private function validateOptionValues(array $options)
    {
        foreach ($this->allowedValues as $option => $allowedValues) {
            if (!in_array($options[$option], $allowedValues, true)) {
                throw new InvalidOptionsException(sprintf('The option "%s" has the value "%s", but is expected to be one of "%s"', $option, $options[$option], implode('", "', $allowedValues)));
            }
        }
    }
}
