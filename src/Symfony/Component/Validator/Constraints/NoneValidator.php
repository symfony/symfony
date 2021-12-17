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
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
class NoneValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof None) {
            throw new UnexpectedTypeException($constraint, None::class);
        }

        if (null === $value) {
            return;
        }

        if (!is_iterable($value)) {
            throw new UnexpectedValueException($value, 'iterable');
        }

        $this->context
            ->getValidator()
            ->inContext($this->context)
            ->validate($value, new Some([
                'constraints' => $constraint->constraints,
                'exactly' => 0,
                'exactMessage' => $constraint->message,
                'includeInternalMessages' => $constraint->includeInternalMessages,
            ]), $this->context->getGroup());
    }
}
