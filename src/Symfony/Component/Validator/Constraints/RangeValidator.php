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
 */
class RangeValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Range) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Range');
        }

        if (null === $value) {
            return;
        }

        if (!is_numeric($value)) {
            $this->buildViolation($constraint->invalidMessage)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->addViolation();

            return;
        }

        if (null !== $constraint->max && $value > $constraint->max) {
            $this->buildViolation($constraint->maxMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ limit }}', $constraint->max)
                ->addViolation();

            return;
        }

        if (null !== $constraint->min && $value < $constraint->min) {
            $this->buildViolation($constraint->minMessage)
                ->setParameter('{{ value }}', $value)
                ->setParameter('{{ limit }}', $constraint->min)
                ->addViolation();
        }
    }
}
