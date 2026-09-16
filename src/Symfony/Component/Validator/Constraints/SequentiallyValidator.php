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
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class SequentiallyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Sequentially) {
            throw new UnexpectedTypeException($constraint, Sequentially::class);
        }

        $context = $this->context;

        $validator = $context->getValidator()->inContext($context);

        $originalCount = $validator->getViolations()->count();

        foreach ($constraint->constraints as $c) {
            $cascadedGroups = $c instanceof Valid && $context instanceof ExecutionContext ? $context->getCascadedGroups() : null;
            $violations = null === $cascadedGroups ? $validator->validate($value, $c) : $validator->validate($value, $c, $cascadedGroups);

            if ($originalCount !== $violations->getViolations()->count()) {
                break;
            }
        }
    }
}
