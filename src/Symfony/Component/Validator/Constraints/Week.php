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
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Week extends Constraint
{
    public const INVALID_FORMAT_ERROR = '19012dd1-01c8-4ce8-959f-72ad22684f5f';
    public const INVALID_WEEK_NUMBER_ERROR = 'd67ebfc9-45fe-4e4c-a038-5eaa56895ea3';
    public const TOO_LOW_ERROR = '9b506423-77a3-4749-aa34-c822a08be978';
    public const TOO_HIGH_ERROR = '85156377-d1e6-42cd-8f6e-dc43c2ecb72b';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::INVALID_WEEK_NUMBER_ERROR => 'INVALID_WEEK_NUMBER_ERROR',
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
    ];

    /**
     * @param non-empty-string|null $min
     * @param non-empty-string|null $max
     */
    public function __construct(
        public ?string $min = null,
        public ?string $max = null,
        public string $invalidFormatMessage = 'This value does not represent a valid week in the ISO 8601 format.',
        public string $invalidWeekNumberMessage = 'This value is not a valid week.',
        public string $tooLowMessage = 'This value should not be before week "{{ min }}".',
        public string $tooHighMessage = 'This value should not be after week "{{ max }}".',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        if (null !== $min && !preg_match('/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/', $min)) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires the min week to be in the ISO 8601 format if set.', __CLASS__));
        }

        if (null !== $max && !preg_match('/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/', $max)) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires the max week to be in the ISO 8601 format if set.', __CLASS__));
        }

        if (null !== $min && null !== $max) {
            [$minYear, $minWeekNumber] = explode('-W', $min, 2);
            [$maxYear, $maxWeekNumber] = explode('-W', $max, 2);

            if ($minYear > $maxYear || ($minYear === $maxYear && $minWeekNumber > $maxWeekNumber)) {
                throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires the min week to be less than or equal to the max week.', __CLASS__));
            }
        }
    }
}
