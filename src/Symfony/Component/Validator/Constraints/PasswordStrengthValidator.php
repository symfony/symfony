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

final class PasswordStrengthValidator extends ConstraintValidator
{
    /**
     * @param (\Closure(string):PasswordStrength::STRENGTH_*)|null $passwordStrengthEstimator
     */
    public function __construct(
        private readonly ?\Closure $passwordStrengthEstimator = null,
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

        if (!\is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }
        $passwordStrengthEstimator = $this->passwordStrengthEstimator ?? self::estimateStrength(...);
        $strength = $passwordStrengthEstimator($value);

        if ($strength < $constraint->minScore) {
            $this->context->buildViolation($constraint->message)
                ->setCode(PasswordStrength::PASSWORD_STRENGTH_ERROR)
                ->addViolation();
        }
    }

    /**
     * Returns the estimated strength of a password.
     *
     * The higher the value, the stronger the password.
     *
     * @return PasswordStrength::STRENGTH_*
     */
    private static function estimateStrength(#[\SensitiveParameter] string $password): int
    {
        $entropy = log(\strlen(count_chars($password, 3)) ** \strlen($password), 2);

        return match (true) {
            $entropy >= 120 => PasswordStrength::STRENGTH_VERY_STRONG,
            $entropy >= 100 => PasswordStrength::STRENGTH_STRONG,
            $entropy >= 80 => PasswordStrength::STRENGTH_REASONABLE,
            $entropy >= 60 => PasswordStrength::STRENGTH_WEAK,
            default => PasswordStrength::STRENGTH_VERY_WEAK,
        };
    }
}
