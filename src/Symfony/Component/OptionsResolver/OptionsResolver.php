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
 * @author Tobias Schultze <http://tobion.de>
 */
class OptionsResolver implements OptionsResolverInterface
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
     * The options without defaults that are required to be passed to resolve().
     * @var array
     */
    private $requiredOptions = array();

    /**
     * A list of accepted values for each option.
     * @var array
     */
    private $allowedValues = array();

    /**
     * A list of accepted types for each option.
     * @var array
     */
    private $allowedTypes = array();

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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function setAllowedValues(array $allowedValues)
    {
        $this->validateOptionsExistence($allowedValues);

        $this->allowedValues = array_replace($this->allowedValues, $allowedValues);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addAllowedValues(array $allowedValues)
    {
        $this->validateOptionsExistence($allowedValues);

        $this->allowedValues = array_merge_recursive($this->allowedValues, $allowedValues);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setAllowedTypes(array $allowedTypes)
    {
        $this->validateOptionsExistence($allowedTypes);

        $this->allowedTypes = array_replace($this->allowedTypes, $allowedTypes);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addAllowedTypes(array $allowedTypes)
    {
        $this->validateOptionsExistence($allowedTypes);

        $this->allowedTypes = array_merge_recursive($this->allowedTypes, $allowedTypes);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setNormalizers(array $normalizers)
    {
        $this->validateOptionsExistence($normalizers);

        foreach ($normalizers as $option => $normalizer) {
            $this->defaultOptions->setNormalizer($option, $normalizer);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isKnown($option)
    {
        return isset($this->knownOptions[$option]);
    }

    /**
     * {@inheritdoc}
     */
    public function isRequired($option)
    {
        return isset($this->requiredOptions[$option]);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(array $options = array())
    {
        $this->validateOptionsExistence($options);
        $this->validateOptionsCompleteness($options);

        // Make sure this method can be called multiple times
        $combinedOptions = clone $this->defaultOptions;

        // Override options set by the user
        foreach ($options as $option => $value) {
            $combinedOptions->set($option, $value);
        }

        // Resolve options
        $resolvedOptions = $combinedOptions->all();

        $this->validateOptionValues($resolvedOptions);
        $this->validateOptionTypes($resolvedOptions);

        return $resolvedOptions;
    }

    /**
     * Validates that the given option names exist and throws an exception
     * otherwise.
     *
     * @param array $options An list of option names as keys.
     *
     * @throws InvalidOptionsException If any of the options has not been defined.
     */
    private function validateOptionsExistence(array $options)
    {
        $diff = array_diff_key($options, $this->knownOptions);

        if (count($diff) > 0) {
            ksort($this->knownOptions);
            ksort($diff);

            throw new InvalidOptionsException(sprintf(
                (count($diff) > 1 ? 'The options "%s" do not exist.' : 'The option "%s" does not exist.').' Known options are: "%s"',
                implode('", "', array_keys($diff)),
                implode('", "', array_keys($this->knownOptions))
            ));
        }
    }

    /**
     * Validates that all required options are given and throws an exception
     * otherwise.
     *
     * @param array $options An list of option names as keys.
     *
     * @throws MissingOptionsException If a required option is missing.
     */
    private function validateOptionsCompleteness(array $options)
    {
        $diff = array_diff_key($this->requiredOptions, $options);

        if (count($diff) > 0) {
            ksort($diff);

            throw new MissingOptionsException(sprintf(
                count($diff) > 1 ? 'The required options "%s" are missing.' : 'The required option "%s" is missing.',
                implode('", "', array_keys($diff))
            ));
        }
    }

    /**
     * Validates that the given option values match the allowed values and
     * throws an exception otherwise.
     *
     * @param array $options A list of option values.
     *
     * @throws InvalidOptionsException If any of the values does not match the
     *                                 allowed values of the option.
     */
    private function validateOptionValues(array $options)
    {
        foreach ($this->allowedValues as $option => $allowedValues) {
            if (isset($options[$option])) {
                if (is_array($allowedValues) && !in_array($options[$option], $allowedValues, true)) {
                    throw new InvalidOptionsException(sprintf('The option "%s" has the value "%s", but is expected to be one of "%s"', $option, $options[$option], implode('", "', $allowedValues)));
                }

                if (is_callable($allowedValues) && !call_user_func($allowedValues, $options[$option])) {
                    throw new InvalidOptionsException(sprintf('The option "%s" has the value "%s", which it is not valid', $option, $options[$option]));
                }
            }
        }
    }

    /**
     * Validates that the given options match the allowed types and
     * throws an exception otherwise.
     *
     * @param array $options A list of options.
     *
     * @throws InvalidOptionsException If any of the types does not match the
     *                                 allowed types of the option.
     */
    private function validateOptionTypes(array $options)
    {
        foreach ($this->allowedTypes as $option => $allowedTypes) {
            if (!array_key_exists($option, $options)) {
                continue;
            }

            $value = $options[$option];
            $allowedTypes = (array) $allowedTypes;

            foreach ($allowedTypes as $type) {
                $isFunction = 'is_'.$type;

                if (function_exists($isFunction) && $isFunction($value)) {
                    continue 2;
                } elseif ($value instanceof $type) {
                    continue 2;
                }
            }

            $printableValue = is_object($value)
                ? get_class($value)
                : (is_array($value)
                    ? 'Array'
                    : (string) $value);

            throw new InvalidOptionsException(sprintf(
                'The option "%s" with value "%s" is expected to be of type "%s"',
                $option,
                $printableValue,
                implode('", "', $allowedTypes)
            ));
        }
    }
}
