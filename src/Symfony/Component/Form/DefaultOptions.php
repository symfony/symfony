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

use Symfony\Component\Form\Exception\OptionDefinitionException;
use Symfony\Component\Form\Exception\InvalidOptionException;

/**
 * Helper for specifying and resolving inter-dependent options.
 *
 * Options are a common pattern for initializing classes in PHP. Avoiding the
 * problems related to this approach is however a non-trivial task. Usually,
 * both classes and subclasses should be able to set default option values.
 * These default options should be overridden by the options passed to the
 * constructor. Last but not least, the (default) values of some options may
 * depend on the values of other options, which themselves may depend on other
 * options and so on.
 *
 * DefaultOptions resolves these problems. It allows you to:
 *
 *  - Define default option values
 *  - Define options in layers that correspond to your class hierarchy. Each
 *    layer may depend on the default value set in the higher layers.
 *  - Define default values for options that depend on the <em>concrete</em>
 *    values of other options.
 *  - Resolve the concrete option values by passing the options set by the
 *    user.
 *
 * You can use it in your classes by implementing the following pattern:
 *
 * <code>
 * class Car
 * {
 *     protected $options;
 *
 *     public function __construct(array $options)
 *     {
 *         $defaultOptions = new DefaultOptions();
 *         $this->addDefaultOptions($defaultOptions);
 *
 *         $this->options = $defaultOptions->resolve($options);
 *     }
 *
 *     protected function addDefaultOptions(DefaultOptions $options)
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
 * against the defined options. If any option is not recognized, an exception
 * is thrown. Finally, resolve() returns the merged default and user options.
 *
 * You can now easily add or override options in subclasses:
 *
 * <code>
 * class Renault extends Car
 * {
 *     protected function addDefaultOptions(DefaultOptions $options)
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
 * new default options!
 *
 * In the previous example, it makes sense to restrict the option "gear" to
 * a set of allowed values:
 *
 * <code>
 * class Renault extends Car
 * {
 *     protected function addDefaultOptions(DefaultOptions $options)
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
 *     protected function addDefaultOptions(DefaultOptions $options)
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
 * The closure receives as its first parameter a container of class Options
 * that contains the <em>concrete</em> options determined upon resolving. The
 * closure is executed once resolve() is called.
 *
 * The closure also receives a second parameter $previousValue that contains the
 * value defined by the parent layer of the hierarchy. If the option has not
 * been defined in any parent layer, the second parameter is NULL.
 *
 * <code>
 * class Renault extends Car
 * {
 *     protected function addDefaultOptions(DefaultOptions $options)
 *     {
 *         $options->add(array(
 *             'country' => function (Options $options, $previousValue) {
 *                 if ('Renault' === $options['make']) {
 *                     return 'FR';
 *                 }
 *
 *                 // return default value defined in Car
 *                 return $previousValue;
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
class DefaultOptions
{
    /**
     * The container resolving the options.
     * @var Options
     */
    private $options;

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
        $this->options = new Options();
    }

    /**
     * Adds default options.
     *
     * @param array $options A list of option names as keys and option values
     *                       as values. The option values may be closures
     *                       of the following signatures:
     *
     *                         - function (Options $options)
     *                         - function (Options $options, $previousValue)
     */
    public function add(array $options)
    {
        foreach ($options as $option => $value) {
            $this->options[$option] = $value;
        }
    }

    /**
     * Adds allowed values for a list of options.
     *
     * @param array $allowedValues A list of option names as keys and arrays
     *                             with values acceptable for that option as
     *                             values.
     *
     * @throws InvalidOptionException If an option has not been defined for
     *                                which an allowed value is set.
     */
    public function addAllowedValues(array $allowedValues)
    {
        $this->validateOptionNames(array_keys($allowedValues));

        $this->allowedValues = array_merge_recursive($this->allowedValues, $allowedValues);
    }

    /**
     * Resolves the final option values by merging default options with user
     * options.
     *
     * @param array $userOptions The options passed by the user.
     *
     * @return array A list of options and their final values.
     *
     * @throws InvalidOptionException    If any of the passed options has not
     *                                   been defined or does not contain an
     *                                   allowed value.
     * @throws OptionDefinitionException If a cyclic dependency is detected
     *                                   between option closures.
     */
    public function resolve(array $userOptions)
    {
        // Make sure this method can be called multiple times
        $options = clone $this->options;

        $this->validateOptionNames(array_keys($userOptions));

        // Override options set by the user
        foreach ($userOptions as $option => $value) {
            $options[$option] = $value;
        }

        // Resolve options
        $options = iterator_to_array($options);

        // Validate against allowed values
        $this->validateOptionValues($options);

        return $options;
    }

    /**
     * Validates that the given option names exist and throws an exception
     * otherwise.
     *
     * @param array $optionNames A list of option names.
     *
     * @throws InvalidOptionException If any of the options has not been
     *                                defined.
     */
    private function validateOptionNames(array $optionNames)
    {
        $knownOptions = $this->options->getNames();
        $diff = array_diff($optionNames, $knownOptions);

        if (count($diff) > 0) {
            sort($knownOptions);
            sort($diff);
        }

        if (count($diff) > 1) {
            throw new InvalidOptionException(sprintf('The options "%s" do not exist. Known options are: "%s"', implode('", "', $diff), implode('", "', $knownOptions)));
        }

        if (count($diff) > 0) {
            throw new InvalidOptionException(sprintf('The option "%s" does not exist. Known options are: "%s"', current($diff), implode('", "', $knownOptions)));
        }
    }

    /**
     * Validates that the given option values match the allowed values and
     * throws an exception otherwise.
     *
     * @param array $options A list of option values.
     *
     * @throws InvalidOptionException If any of the values does not match the
     *                                allowed values of the option.
     */
    private function validateOptionValues(array $options)
    {
        foreach ($this->allowedValues as $option => $allowedValues) {
            if (!in_array($options[$option], $allowedValues, true)) {
                throw new InvalidOptionException(sprintf('The option "%s" has the value "%s", but is expected to be one of "%s"', $option, $options[$option], implode('", "', $allowedValues)));
            }
        }
    }
}
