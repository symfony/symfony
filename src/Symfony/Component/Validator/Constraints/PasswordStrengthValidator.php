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
use Symfony\Component\Validator\Password\PasswordStrengthEstimatorInterface;

final class PasswordStrengthValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PasswordStrengthEstimatorInterface $passwordStrengthEstimator
    ) {
    }

    public function validate(#[\SensitiveParameter] mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PasswordStrength) {
            throw new UnexpectedTypeException($constraint, PasswordStrength::class);
        }

        if (null === $value) {
            return;
        }

        if (!\is_string($value) && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $strength = $this->passwordStrengthEstimator->estimateStrength((string) $value);

        if ($strength < $constraint->minScore) {
            $this->context->buildViolation($constraint->message)
                ->setCode(PasswordStrength::PASSWORD_STRENGTH_ERROR)
                ->setParameter('{{ strength }}', $strength)
                ->addViolation();
        }
    }
}
