<?php

namespace Symfony\Component\Validator\Password;

use Stringable;

interface PasswordStrengthEstimatorInterface
{
    /**
     * Returns the estimated strength of a password.
     *
     * The higher the value, the stronger the password.
     *
     * @return PasswordStrength::STRENGTH_*
     */
    public function estimateStrength(#[\SensitiveParameter] string|Stringable $password): int;
}
