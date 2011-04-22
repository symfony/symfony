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
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class ChoiceValidator extends ConstraintValidator
{
    public function isValid($value, Constraint $constraint)
    {
        if (!$constraint->choices && !$constraint->callback) {
            throw new ConstraintDefinitionException('Either "choices" or "callback" must be specified on constraint Choice');
        }

        if (null === $value) {
            return true;
        }

        if ($constraint->multiple && !is_array($value)) {
            throw new UnexpectedTypeException($value, 'array');
        }

        if ($constraint->callback) {
            if (is_callable(array($this->context->getCurrentClass(), $constraint->callback))) {
                $choices = call_user_func(array($this->context->getCurrentClass(), $constraint->callback));
            } else if (is_callable($constraint->callback)) {
                $choices = call_user_func($constraint->callback);
            } else {
                throw new ConstraintDefinitionException('The Choice constraint expects a valid callback');
            }
        } else {
            $choices = $constraint->choices;
        }

        if ($constraint->multiple) {
            foreach ($value as $_value) {
                if (!in_array($_value, $choices, true)) {
                    $this->setMessage($constraint->multipleMessage, array('{{ value }}' => $_value));

                    return false;
                }
            }

            $count = count($value);

            if ($constraint->min !== null && $count < $constraint->min) {
                $this->setMessage($constraint->minMessage, array('{{ limit }}' => $constraint->min));

                return false;
            }

            if ($constraint->max !== null && $count > $constraint->max) {
                $this->setMessage($constraint->maxMessage, array('{{ limit }}' => $constraint->max));

                return false;
            }
        } elseif (!in_array($value, $choices, true)) {
            $this->setMessage($constraint->message, array('{{ value }}' => $value));

            return false;
        }

        return true;
    }
}
