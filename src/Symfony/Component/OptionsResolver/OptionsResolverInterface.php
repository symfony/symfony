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

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface OptionsResolverInterface
{
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
     *                             values or closures as values.
     *
     * @return OptionsResolverInterface The resolver instance.
     */
    public function setDefaults(array $defaultValues);

    /**
     * Replaces default option values.
     *
     * Old defaults are erased, which means that closures passed here cannot
     * access the previous default value. This may be useful to improve
     * performance if the previous default value is calculated by an expensive
     * closure.
     *
     * @param array $defaultValues A list of option names as keys and default
     *                             values or closures as values.
     *
     * @return OptionsResolverInterface The resolver instance.
     */
    public function replaceDefaults(array $defaultValues);

    /**
     * Sets optional options.
     *
     * This method declares valid option names without setting default values for them.
     * If these options are not passed to {@link resolve()} and no default has been set
     * for them, they will be missing in the final options array. This can be helpful
     * if you want to determine whether an option has been set or not because otherwise
     * {@link resolve()} would trigger an exception for unknown options.
     *
     * @param array $optionNames A list of option names.
     *
     * @return OptionsResolverInterface The resolver instance.
     *
     * @throws Exception\OptionDefinitionException When trying to pass default values.
     */
    public function setOptional(array $optionNames);

    /**
     * Sets required options.
     *
     * If these options are not passed to {@link resolve()} and no default has been set for
     * them, an exception will be thrown.
     *
     * @param array $optionNames A list of option names.
     *
     * @return OptionsResolverInterface The resolver instance.
     *
     * @throws Exception\OptionDefinitionException When trying to pass default values.
     */
    public function setRequired(array $optionNames);

    /**
     * Sets allowed values for a list of options.
     *
     * @param array $allowedValues A list of option names as keys and arrays
     *                             with values acceptable for that option as
     *                             values.
     *
     * @return OptionsResolverInterface The resolver instance.
     *
     * @throws Exception\InvalidOptionsException If an option has not been defined
     *                                 (see {@link isKnown()}) for which
     *                                 an allowed value is set.
     */
    public function setAllowedValues(array $allowedValues);

    /**
     * Adds allowed values for a list of options.
     *
     * The values are merged with the allowed values defined previously.
     *
     * @param array $allowedValues A list of option names as keys and arrays
     *                             with values acceptable for that option as
     *                             values.
     *
     * @return OptionsResolverInterface The resolver instance.
     *
     * @throws Exception\InvalidOptionsException If an option has not been defined
     *                                 (see {@link isKnown()}) for which
     *                                 an allowed value is set.
     */
    public function addAllowedValues(array $allowedValues);

    /**
     * Sets allowed types for a list of options.
     *
     * @param array $allowedTypes A list of option names as keys and type
     *                            names passed as string or array as values.
     *
     * @return OptionsResolverInterface The resolver instance.
     *
     * @throws Exception\InvalidOptionsException If an option has not been defined for
     *                                           which an allowed type is set.
     */
    public function setAllowedTypes(array $allowedTypes);

    /**
     * Adds allowed types for a list of options.
     *
     * The types are merged with the allowed types defined previously.
     *
     * @param array $allowedTypes A list of option names as keys and type
     *                            names passed as string or array as values.
     *
     * @return OptionsResolverInterface The resolver instance.
     *
     * @throws Exception\InvalidOptionsException If an option has not been defined for
     *                                           which an allowed type is set.
     */
    public function addAllowedTypes(array $allowedTypes);

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
     * @param array $normalizers An array of closures.
     *
     * @return OptionsResolverInterface The resolver instance.
     */
    public function setNormalizers(array $normalizers);

    /**
     * Returns whether an option is known.
     *
     * An option is known if it has been passed to either {@link setDefaults()},
     * {@link setRequired()} or {@link setOptional()} before.
     *
     * @param string $option The name of the option.
     *
     * @return bool    Whether the option is known.
     */
    public function isKnown($option);

    /**
     * Returns whether an option is required.
     *
     * An option is required if it has been passed to {@link setRequired()},
     * but not to {@link setDefaults()}. That is, the option has been declared
     * as required and no default value has been set.
     *
     * @param string $option The name of the option.
     *
     * @return bool    Whether the option is required.
     */
    public function isRequired($option);

    /**
     * Returns the combination of the default and the passed options.
     *
     * @param array $options The custom option values.
     *
     * @return array A list of options and their values.
     *
     * @throws Exception\InvalidOptionsException   If any of the passed options has not
     *                                             been defined or does not contain an
     *                                             allowed value.
     * @throws Exception\MissingOptionsException   If a required option is missing.
     * @throws Exception\OptionDefinitionException If a cyclic dependency is detected
     *                                             between two lazy options.
     */
    public function resolve(array $options = array());
}
