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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Validates that the given password has reached a minimum strength.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PasswordStrength extends Constraint
{
    public const STRENGTH_VERY_WEAK = 0;
    public const STRENGTH_WEAK = 1;
    public const STRENGTH_MEDIUM = 2;
    public const STRENGTH_STRONG = 3;
    public const STRENGTH_VERY_STRONG = 4;

    public const PASSWORD_STRENGTH_ERROR = '4234df00-45dd-49a4-b303-a75dbf8b10d8';

    protected const ERROR_NAMES = [
        self::PASSWORD_STRENGTH_ERROR => 'PASSWORD_STRENGTH_ERROR',
    ];

    public string $message = 'The password strength is too low. Please use a stronger password.';

    public int $minScore;

    /**
     * @param array<string,mixed>|null $options
     * @param self::STRENGTH_*|null    $minScore The minimum required strength of the password (defaults to {@see PasswordStrength::STRENGTH_MEDIUM})
     * @param string[]|null            $groups
     */
    #[HasNamedArguments]
    public function __construct(?array $options = null, ?int $minScore = null, ?array $groups = null, mixed $payload = null, ?string $message = null)
    {
        if ($options) {
            trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        $options['minScore'] ??= self::STRENGTH_MEDIUM;

        parent::__construct($options, $groups, $payload);

        $this->minScore = $minScore ?? $this->minScore;
        $this->message = $message ?? $this->message;

        if ($this->minScore < 1 || 4 < $this->minScore) {
            throw new ConstraintDefinitionException(\sprintf('The parameter "minScore" of the "%s" constraint must be an integer between 1 and 4.', self::class));
        }
    }
}
