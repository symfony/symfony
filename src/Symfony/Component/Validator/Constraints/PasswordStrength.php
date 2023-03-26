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
use Symfony\Component\Validator\Exception\LogicException;
use ZxcvbnPhp\Zxcvbn;

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
    public const PASSWORD_STRENGTH_ERROR = '4234df00-45dd-49a4-b303-a75dbf8b10d8';
    public const RESTRICTED_USER_INPUT_ERROR = 'd187ff45-bf23-4331-aa87-c24a36e9b400';

    protected const ERROR_NAMES = [
        self::PASSWORD_STRENGTH_ERROR => 'PASSWORD_STRENGTH_ERROR',
        self::RESTRICTED_USER_INPUT_ERROR => 'RESTRICTED_USER_INPUT_ERROR',
    ];

    public string $lowStrengthMessage = 'The password strength is too low. Please use a stronger password.';

    public int $minScore = 2;

    public string $restrictedDataMessage = 'The password contains the following restricted data: {{ wordList }}.';

    /**
     * @var array<string>
     */
    public array $restrictedData = [];

    public function __construct(mixed $options = null, array $groups = null, mixed $payload = null)
    {
        if (!class_exists(Zxcvbn::class)) {
            throw new LogicException(sprintf('The "%s" class requires the "bjeavons/zxcvbn-php" library. Try running "composer require bjeavons/zxcvbn-php".', self::class));
        }

        if (isset($options['minScore']) && (!\is_int($options['minScore']) || $options['minScore'] < 1 || $options['minScore'] > 4)) {
            throw new ConstraintDefinitionException(sprintf('The parameter "minScore" of the "%s" constraint must be an integer between 1 and 4.', static::class));
        }

        if (isset($options['restrictedData'])) {
            array_walk($options['restrictedData'], static function (mixed $value): void {
                if (!\is_string($value)) {
                    throw new ConstraintDefinitionException(sprintf('The parameter "restrictedData" of the "%s" constraint must be a list of strings.', static::class));
                }
            });
        }
        parent::__construct($options, $groups, $payload);
    }
}
