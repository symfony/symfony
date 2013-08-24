<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Provides a base class for the validation of property comparisons.
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 *
 * @since v2.3.0
 */
abstract class AbstractComparisonValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     *
     * @since v2.3.0
     */
    public function validate($value, Constraint $constraint)
    {
        if (null === $value) {
            return;
        }

        if (!$this->compareValues($value, $constraint->value, $constraint)) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $this->valueToString($constraint->value),
                '{{ compared_value }}' => $this->valueToString($constraint->value),
                '{{ compared_value_type }}' => $this->valueToType($constraint->value)
            ));
        }
    }

    /**
     * Returns a string representation of the type of the value.
     *
     * @param  mixed $value
     *
     * @return string
     *
     * @since v2.3.0
     */
    private function valueToType($value)
    {
        return is_object($value) ? get_class($value) : gettype($value);
    }

    /**
     * Returns a string representation of the value.
     *
     * @param  mixed  $value
     *
     * @return string
     *
     * @since v2.3.0
     */
    private function valueToString($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d H:i:s');
        }

        return var_export($value, true);
    }

    /**
     * Compares the two given values to find if their relationship is valid
     *
     * @param mixed      $value1     The first value to compare
     * @param mixed      $value2     The second value to compare
     *
     * @return Boolean true if the relationship is valid, false otherwise
     *
     * @since v2.3.0
     */
    abstract protected function compareValues($value1, $value2);
}
