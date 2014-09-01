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
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class TimeValidator extends ConstraintValidator
{
    /**
     * {@inheritDoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Time) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Time');
        }

        if (null === $value || '' === $value || $value instanceof \DateTime) {
            return;
        }

        if (!is_scalar($value) && !(is_object($value) && method_exists($value, '__toString'))) {
            throw new UnexpectedTypeException($value, 'string');
        }

        $value = (string) $value;

        $pattern = $this->getPattern($constraint->withMinutes, $constraint->withSeconds);

        if (!preg_match($pattern, $value)) {
            $this->context->addViolation($constraint->message, array('{{ value }}' => $value));
        }
    }

    /**
     * Returns the regex pattern for validating
     *
     * @param Boolean $withMinutes
     * @param Boolean $withSeconds
     * @return string
     */
    protected function getPattern($withMinutes, $withSeconds)
    {
        // pattern for hours
        $pattern = "(0?[0-9]|1[0-9]|2[0-3])";

        if ($withMinutes) {
            // pattern for minutes
            $pattern .= "(:([0-5][0-9]))";

            if ($withSeconds) {
                // because the pattern for seconds is the same as that for minutes, we repeat it twice
                $pattern .= "{2}";
            }
        }

        return "/^".$pattern."$/";
    }
}
