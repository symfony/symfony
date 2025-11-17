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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WordCount extends Constraint
{
    public const TOO_SHORT_ERROR = 'cc4925df-b5a6-42dd-87f3-21919f349bf3';
    public const TOO_LONG_ERROR = 'a951a642-f662-4fad-8761-79250eef74cb';

    protected const ERROR_NAMES = [
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
    ];

    /**
     * @param int<0, max>|null  $min
     * @param positive-int|null $max
     */
    public function __construct(
        public ?int $min = null,
        public ?int $max = null,
        public ?string $locale = null,
        public string $minMessage = 'This value is too short. It should contain at least one word.|This value is too short. It should contain at least {{ min }} words.',
        public string $maxMessage = 'This value is too long. It should contain one word.|This value is too long. It should contain {{ max }} words or less.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (!class_exists(\IntlBreakIterator::class)) {
            throw new \RuntimeException(\sprintf('The "%s" constraint requires the "intl" PHP extension.', __CLASS__));
        }

        if (null === $min && null === $max) {
            throw new MissingOptionsException(\sprintf('Either option "min" or "max" must be given for constraint "%s".', __CLASS__), ['min', 'max']);
        }

        if (null !== $min && $min <= 0) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires the min word count to be a positive integer if set.', __CLASS__));
        }

        if (null !== $max && $max <= 0) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires the max word count to be a positive integer if set.', __CLASS__));
        }

        if (null !== $min && null !== $max && $min > $max) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires the min word count to be less than or equal to the max word count.', __CLASS__));
        }

        parent::__construct(null, $groups, $payload);
    }
}
