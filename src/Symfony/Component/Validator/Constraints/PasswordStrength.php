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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 *
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
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

    public function __construct(array $options = null, int $minScore = null, array $groups = null, mixed $payload = null)
    {
        $options['minScore'] ??= self::STRENGTH_MEDIUM;

        parent::__construct($options, $groups, $payload);

        $this->minScore = $minScore ?? $this->minScore;

        if ($this->minScore < 1 || 4 < $this->minScore) {
            throw new ConstraintDefinitionException(sprintf('The parameter "minScore" of the "%s" constraint must be an integer between 1 and 4.', self::class));
        }
    }
}
