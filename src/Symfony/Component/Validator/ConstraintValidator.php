<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

/**
 * Base class for constraint validators
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
abstract class ConstraintValidator implements ConstraintValidatorInterface
{
    /**
     * @var ExecutionContextInterface
     */
    protected $context;

    /**
     * {@inheritdoc}
     */
    public function initialize(ExecutionContextInterface $context)
    {
        $this->context = $context;
    }

    /**
     * Returns a string representation of the type of the value.
     *
     * @param  mixed $value
     *
     * @return string
     */
    protected function valueToType($value)
    {
        return is_object($value) ? get_class($value) : gettype($value);
    }

    /**
     * Returns a string representation of the value.
     *
     * @param  mixed  $value
     *
     * @return string
     */
    protected function valueToString($value)
    {
        if ($value instanceof \DateTime) {
            if (class_exists('IntlDateFormatter')) {
                $locale = \Locale::getDefault();
                $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);

                return $formatter->format($value);
            }

            return $value->format('Y-m-d H:i:s');
        }

        if (is_object($value)) {
            return 'Object('.get_class($value).')';
        }

        if (is_array($value)) {
            return 'Array';
        }

        if (is_string($value)) {
            return '"'.$value.'"';
        }

        if (is_resource($value)) {
            return sprintf('Resource(%s#%d)', get_resource_type($value), $value);
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
}
