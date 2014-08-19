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
use Symfony\Component\Validator\Context\ExecutionContextInterface;
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
        if (!$constraint instanceof Choice) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__.'\Choice');
        }

        if (!$constraint->choices && !$constraint->callback) {
            throw new ConstraintDefinitionException('Either "choices" or "callback" must be specified on constraint Choice');
        }

        if (null === $value) {
            return;
        }

        if ($constraint->multiple && !is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        if ($constraint->callback) {
            if (is_callable(array($this->context->getClassName(), $constraint->callback))) {
                $choices = call_user_func(array($this->context->getClassName(), $constraint->callback));
            } elseif (is_callable($constraint->callback)) {
                $choices = call_user_func($constraint->callback);
            } else {
                throw new ConstraintDefinitionException('The Choice constraint expects a valid callback');
            }
        } else {
            $choices = $constraint->choices;
        }

        if ($constraint->multiple) {
            foreach ($value as $_value) {
                if (!in_array($_value, $choices, $constraint->strict)) {
                    if ($this->context instanceof ExecutionContextInterface) {
                        $this->context->buildViolation($constraint->multipleMessage)
                            ->setParameter('{{ value }}', $this->formatValue($_value))
                            ->addViolation();
                    } else {
                        // 2.4 API
                        $this->context->addViolation($constraint->multipleMessage, array(
                            '{{ value }}' => $this->formatValue($_value),
                        ));
                    }
                }
            }

            $count = count($value);

            if ($constraint->min !== null && $count < $constraint->min) {
                if ($this->context instanceof ExecutionContextInterface) {
                    $this->context->buildViolation($constraint->minMessage)
                        ->setParameter('{{ limit }}', $constraint->min)
                        ->setPlural((int) $constraint->min)
                        ->addViolation();
                } else {
                    // 2.4 API
                    $this->context->addViolation($constraint->minMessage, array(
                        '{{ limit }}' => $constraint->min
                    ), $value, (int) $constraint->min);
                }

                return;
            }

            if ($constraint->max !== null && $count > $constraint->max) {
                if ($this->context instanceof ExecutionContextInterface) {
                    $this->context->buildViolation($constraint->maxMessage)
                        ->setParameter('{{ limit }}', $constraint->max)
                        ->setPlural((int) $constraint->max)
                        ->addViolation();
                } else {
                    // 2.4 API
                    $this->context->addViolation($constraint->maxMessage, array(
                        '{{ limit }}' => $constraint->max
                    ), $value, (int) $constraint->max);
                }

                return;
            }
        } elseif (!in_array($value, $choices, $constraint->strict)) {
            if ($this->context instanceof ExecutionContextInterface) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ value }}', $this->formatValue($value))
                    ->addViolation();
            } else {
                // 2.4 API
                $this->context->addViolation($constraint->message, array(
                    '{{ value }}' => $this->formatValue($value),
                ));
            }
        }
    }
}
