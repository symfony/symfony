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
 * Stores option configuration.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @since 2.6
 */
class OptionsConfig
{
    /**
     * The default option values.
     *
     * @var Options
     *
     * @internal Public for performance reasons. Should not be accessed by user
     *           code.
     */
    public $defaultOptions;

    /**
     * The options known by the resolver.
     *
     * @var array
     *
     * @internal Public for performance reasons. Should not be accessed by user
     *           code.
     */
    public $knownOptions = array();

    /**
     * The options without defaults that are required to be passed to resolve().
     *
     * @var array
     *
     * @internal Public for performance reasons. Should not be accessed by user
     *           code.
     */
    public $requiredOptions = array();

    /**
     * A list of accepted values for each option.
     *
     * @var array
     *
     * @internal Public for performance reasons. Should not be accessed by user
     *           code.
     */
    public $allowedValues = array();

    /**
     * A list of accepted types for each option.
     *
     * @var array
     *
     * @internal Public for performance reasons. Should not be accessed by user
     *           code.
     */
    public $allowedTypes = array();

    /**
     * Creates a new instance.
     */
    public function __construct()
    {
        $this->defaultOptions = new Options();
    }

    /**
     * Clones the resolver.
     */
    public function __clone()
    {
        $this->defaultOptions = clone $this->defaultOptions;
    }

    /**
     * Sets default option values.
     *
     * The options can either be values of any types or closures that
     * evaluate the option value lazily. These closures must have one
     * of the following signatures:
     *
     * <code>
     * function (Options $options)
     * function (Options $options, $value)
     * </code>
     *
     * The second parameter passed to the closure is the previously
     * set default value, in case you are overwriting an existing
     * default value.
     *
     * The closures should return the lazily created option value.
     *
     * @param array $defaultValues A list of option names as keys and default
     *                             values or closures as values
     *
     * @return OptionsConfig This configuration instance
     */
    public function setDefaults(array $defaultValues)
    {
        foreach ($defaultValues as $option => $value) {
            $this->defaultOptions->overload($option, $value);
            $this->knownOptions[$option] = true;
            unset($this->requiredOptions[$option]);
        }

        return $this;
    }

    /**
     * Replaces default option values.
     *
     * Old defaults are erased, which means that closures passed here cannot
     * access the previous default value. This may be useful to improve
     * performance if the previous default value is calculated by an expensive
     * closure.
     *
     * @param array $defaultValues A list of option names as keys and default
     *                             values or closures as values
     *
     * @return OptionsConfig This configuration instance
     */
    public function replaceDefaults(array $defaultValues)
    {
        foreach ($defaultValues as $option => $value) {
            $this->defaultOptions->set($option, $value);
            $this->knownOptions[$option] = true;
            unset($this->requiredOptions[$option]);
        }

        return $this;
    }

    /**
     * Sets optional options.
     *
     * This method declares valid option names without setting default values for them.
     * If these options are not passed to {@link resolve()} and no default has been set
     * for them, they will be missing in the final options array. This can be helpful
     * if you want to determine whether an option has been set or not because otherwise
     * {@link resolve()} would trigger an exception for unknown options.
     *
     * @param array $optionNames A list of option names
     *
     * @return OptionsConfig This configuration instance
     *
     * @throws Exception\OptionDefinitionException When trying to pass default values
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
     * If these options are not passed to {@link resolve()} and no default has been set for
     * them, an exception will be thrown.
     *
     * @param array $optionNames A list of option names
     *
     * @return OptionsConfig This configuration instance
     *
     * @throws Exception\OptionDefinitionException When trying to pass default values
     */
    public function setRequired(array $optionNames)
    {
        foreach ($optionNames as $key => $option) {
            if (!is_int($key)) {
                throw new OptionDefinitionException('You should not pass default values to setRequired()');
            }

            $this->knownOptions[$option] = true;
            // set as required if no default has been set already
            if (!isset($this->defaultOptions[$option])) {
                $this->requiredOptions[$option] = true;
            }
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
     * @return OptionsConfig This configuration instance
     *
     * @throws Exception\InvalidOptionsException If an option has not been defined
     *                                 (see {@link isKnown()}) for which
     *                                 an allowed value is set
     */
    public function setAllowedValues(array $allowedValues)
    {
        Options::validateNames($allowedValues, $this->knownOptions, true);

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
     *                             values
     *
     * @return OptionsConfig This configuration instance
     *
     * @throws Exception\InvalidOptionsException If an option has not been defined
     *                                 (see {@link isKnown()}) for which
     *                                 an allowed value is set
     */
    public function addAllowedValues(array $allowedValues)
    {
        Options::validateNames($allowedValues, $this->knownOptions, true);

        $this->allowedValues = array_merge_recursive($this->allowedValues, $allowedValues);

        return $this;
    }

    /**
     * Sets allowed types for a list of options.
     *
     * @param array $allowedTypes A list of option names as keys and type
     *                            names passed as string or array as values
     *
     * @return OptionsConfig This configuration instance
     *
     * @throws Exception\InvalidOptionsException If an option has not been defined for
     *                                           which an allowed type is set
     */
    public function setAllowedTypes(array $allowedTypes)
    {
        Options::validateNames($allowedTypes, $this->knownOptions, true);

        $this->allowedTypes = array_replace($this->allowedTypes, $allowedTypes);

        return $this;
    }

    /**
     * Adds allowed types for a list of options.
     *
     * The types are merged with the allowed types defined previously.
     *
     * @param array $allowedTypes A list of option names as keys and type
     *                            names passed as string or array as values
     *
     * @return OptionsConfig This configuration instance
     *
     * @throws Exception\InvalidOptionsException If an option has not been defined for
     *                                           which an allowed type is set
     */
    public function addAllowedTypes(array $allowedTypes)
    {
        Options::validateNames($allowedTypes, $this->knownOptions, true);

        $this->allowedTypes = array_merge_recursive($this->allowedTypes, $allowedTypes);

        return $this;
    }

    /**
     * Sets normalizers that are applied on resolved options.
     *
     * The normalizers should be closures with the following signature:
     *
     * <code>
     * function (Options $options, $value)
     * </code>
     *
     * The second parameter passed to the closure is the value of
     * the option.
     *
     * The closure should return the normalized value.
     *
     * @param array $normalizers An array of closures
     *
     * @return OptionsConfig This configuration instance
     */
    public function setNormalizers(array $normalizers)
    {
        Options::validateNames($normalizers, $this->knownOptions, true);

        foreach ($normalizers as $option => $normalizer) {
            $this->defaultOptions->setNormalizer($option, $normalizer);
        }

        return $this;
    }

    /**
     * Returns whether an option is known.
     *
     * An option is known if it has been passed to either {@link setDefaults()},
     * {@link setRequired()} or {@link setOptional()} before.
     *
     * @param string $option The name of the option
     *
     * @return bool Whether the option is known
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
     * @param string $option The name of the option
     *
     * @return bool Whether the option is required
     */
    public function isRequired($option)
    {
        return isset($this->requiredOptions[$option]);
    }
}
