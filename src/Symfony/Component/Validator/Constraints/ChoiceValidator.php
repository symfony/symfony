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
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * ChoiceValidator validates that the value is one of the expected values.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Florian Eckerstorfer <florian@eckerstorfer.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ChoiceValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Choice) {
            throw new UnexpectedTypeException($constraint, Choice::class);
        }

        if (!\is_array($constraint->choices) && !$constraint->callback) {
            throw new ConstraintDefinitionException('Either "choices" or "callback" must be specified on constraint Choice.');
        }

        if (null === $value) {
            return;
        }

        if ($constraint->multiple && !\is_array($value)) {
            throw new UnexpectedValueException($value, 'array');
        }

        if ($constraint->callback) {
            if (!\is_callable($choices = [$this->context->getObject(), $constraint->callback])
                && !\is_callable($choices = [$this->context->getClassName(), $constraint->callback])
                && !\is_callable($choices = $constraint->callback)
            ) {
                throw new ConstraintDefinitionException('The Choice constraint expects a valid callback.');
            }
            $choices = $choices();
            if (!\is_array($choices)) {
                throw new ConstraintDefinitionException(\sprintf('The Choice constraint callback "%s" is expected to return an array, but returned "%s".', trim($this->formatValue($constraint->callback), '"'), get_debug_type($choices)));
            }
        } else {
            $choices = $constraint->choices;
        }

        if (true !== $constraint->strict) {
            throw new RuntimeException('The "strict" option of the Choice constraint should not be used.');
        }

        if ($constraint->multiple) {
            foreach ($value as $_value) {
                if ($constraint->match xor \in_array($_value, $choices, true)) {
                    $this->context->buildViolation($constraint->multipleMessage)
                        ->setParameter('{{ value }}', $this->formatValue($_value))
                        ->setParameter('{{ choices }}', $this->formatValues($choices))
                        ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
                        ->setInvalidValue($_value)
                        ->addViolation();

                    return;
                }
            }

            $count = \count($value);

            if (null !== $constraint->min && $count < $constraint->min) {
                $this->context->buildViolation($constraint->minMessage)
                    ->setParameter('{{ limit }}', $constraint->min)
                    ->setPlural($constraint->min)
                    ->setCode(Choice::TOO_FEW_ERROR)
                    ->addViolation();

                return;
            }

            if (null !== $constraint->max && $count > $constraint->max) {
                $this->context->buildViolation($constraint->maxMessage)
                    ->setParameter('{{ limit }}', $constraint->max)
                    ->setPlural($constraint->max)
                    ->setCode(Choice::TOO_MANY_ERROR)
                    ->addViolation();
            }
        } elseif ($constraint->match xor \in_array($value, $choices, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setParameter('{{ choices }}', $this->formatValues($choices))
                ->setCode(Choice::NO_SUCH_CHOICE_ERROR)
                ->addViolation();
        }
    }
}
