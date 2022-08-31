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
 * @author Dynèsh Hassanaly <artyum@protonmail.com>
 */
class ControllerArgumentValidator extends ConstraintValidator
{
    /**
     * @inheritDoc
     */
    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof ControllerArgument) {
            throw new UnexpectedTypeException($constraint, ControllerArgument::class);
        }

        if (null === $value) {
            return;
        }

        $context = $this->context;
        $validator = $context->getValidator()->inContext($context);

        foreach ($constraint->constraints as $c) {
            $validator->validate($value, $c);
        }
    }
}
