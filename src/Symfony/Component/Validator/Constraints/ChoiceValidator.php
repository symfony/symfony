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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * ChoiceValidator validates that the value is one of the expected values.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class ChoiceValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (in_array($constraint->choices, array(null, false), true) && !$constraint->callback) {
            throw new ConstraintDefinitionException('Either "choices" or "callback" must be specified on constraint Choice');
        }

        if (!$constraint->callback && !is_array($constraint->choices)) {
            throw new UnexpectedTypeException($constraint->choices, 'array');
        }

        if (null === $value) {
            return;
        }

        if ($constraint->multiple && !is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        if ($constraint->callback) {
            if (!is_callable($choices = array($this->context->getClassName(), $constraint->callback))
                && !is_callable($choices = $constraint->callback)
            ) {
                throw new ConstraintDefinitionException('The Choice constraint expects a valid callback');
            }
            $choices = call_user_func($choices);
        } else {
            $choices = $constraint->choices;
        }

        if ($constraint->multiple) {
            foreach ($value as $_value) {
                if (!in_array($_value, $choices, $constraint->strict)) {
                    $this->context->addViolation($constraint->multipleMessage, array(
                        '{{ value }}' => $this->formatValue($_value),
                    ));
                }
            }

            $count = count($value);

            if ($constraint->min !== null && $count < $constraint->min) {
                $this->context->addViolation($constraint->minMessage, array(
                    '{{ limit }}' => $constraint->min,
                ), $value, (int) $constraint->min);

                return;
            }

            if ($constraint->max !== null && $count > $constraint->max) {
                $this->context->addViolation($constraint->maxMessage, array(
                    '{{ limit }}' => $constraint->max,
                ), $value, (int) $constraint->max);

                return;
            }
        } elseif (!in_array($value, $choices, $constraint->strict)) {
            $this->context->addViolation($constraint->message, array(
                '{{ value }}' => $this->formatValue($value),
            ));
        }
    }
}
