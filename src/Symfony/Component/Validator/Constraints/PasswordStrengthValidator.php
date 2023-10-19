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
        if (!$length = \strlen($password)) {
            return PasswordStrength::STRENGTH_VERY_WEAK;
        }
        $password = count_chars($password, 1);
        $chars = \count($password);

        $control = $digit = $upper = $lower = $symbol = $other = 0;
        foreach ($password as $chr => $count) {
            match (true) {
                $chr < 32 || 127 === $chr => $control = 33,
                48 <= $chr && $chr <= 57 => $digit = 10,
                65 <= $chr && $chr <= 90 => $upper = 26,
                97 <= $chr && $chr <= 122 => $lower = 26,
                128 <= $chr => $other = 128,
                default => $symbol = 33,
            };
        }

        $pool = $lower + $upper + $digit + $symbol + $control + $other;
        $entropy = $chars * log($pool, 2) + ($length - $chars) * log($chars, 2);

        return match (true) {
            $entropy >= 120 => PasswordStrength::STRENGTH_VERY_STRONG,
            $entropy >= 100 => PasswordStrength::STRENGTH_STRONG,
            $entropy >= 80 => PasswordStrength::STRENGTH_MEDIUM,
            $entropy >= 60 => PasswordStrength::STRENGTH_WEAK,
            default => PasswordStrength::STRENGTH_VERY_WEAK,
        };
    }
}
