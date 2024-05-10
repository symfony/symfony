<?php

namespace Symfony\Component\Validator\Password;

use Stringable;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class PasswordStrengthEstimator implements PasswordStrengthEstimatorInterface
{
    public function estimateStrength(#[\SensitiveParameter] string|Stringable $password): int
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
