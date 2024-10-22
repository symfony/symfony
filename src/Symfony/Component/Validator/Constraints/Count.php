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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Validates a collection's element count.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Count extends Constraint
{
    public const TOO_FEW_ERROR = 'bef8e338-6ae5-4caf-b8e2-50e7b0579e69';
    public const TOO_MANY_ERROR = '756b1212-697c-468d-a9ad-50dd783bb169';
    public const NOT_EQUAL_COUNT_ERROR = '9fe5d43f-3784-4ece-a0e1-473fc02dadbc';
    public const NOT_DIVISIBLE_BY_ERROR = DivisibleBy::NOT_DIVISIBLE_BY;

    protected const ERROR_NAMES = [
        self::TOO_FEW_ERROR => 'TOO_FEW_ERROR',
        self::TOO_MANY_ERROR => 'TOO_MANY_ERROR',
        self::NOT_EQUAL_COUNT_ERROR => 'NOT_EQUAL_COUNT_ERROR',
        self::NOT_DIVISIBLE_BY_ERROR => 'NOT_DIVISIBLE_BY_ERROR',
    ];

    public string $minMessage = 'This collection should contain {{ limit }} element or more.|This collection should contain {{ limit }} elements or more.';
    public string $maxMessage = 'This collection should contain {{ limit }} element or less.|This collection should contain {{ limit }} elements or less.';
    public string $exactMessage = 'This collection should contain exactly {{ limit }} element.|This collection should contain exactly {{ limit }} elements.';
    public string $divisibleByMessage = 'The number of elements in this collection should be a multiple of {{ compared_value }}.';
    public ?int $min = null;
    public ?int $max = null;
    public ?int $divisibleBy = null;

    /**
     * @param int<0, max>|array<string,mixed>|null $exactly     The exact expected number of elements
     * @param int<0, max>|null                     $min         Minimum expected number of elements
     * @param positive-int|null                    $max         Maximum expected number of elements
     * @param positive-int|null                    $divisibleBy The number the collection count should be divisible by
     * @param string[]|null                        $groups
     * @param array<mixed,string>|null             $options
     */
    #[HasNamedArguments]
    public function __construct(
        int|array|null $exactly = null,
        ?int $min = null,
        ?int $max = null,
        ?int $divisibleBy = null,
        ?string $exactMessage = null,
        ?string $minMessage = null,
        ?string $maxMessage = null,
        ?string $divisibleByMessage = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $options = null,
    ) {
        if (\is_array($exactly)) {
            trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            $options = array_merge($exactly, $options ?? []);
            $exactly = $options['value'] ?? null;
        } elseif (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        } else {
            $options = [];
        }

        $min ??= $options['min'] ?? null;
        $max ??= $options['max'] ?? null;

        unset($options['value'], $options['min'], $options['max']);

        if (null !== $exactly && null === $min && null === $max) {
            $min = $max = $exactly;
        }

        parent::__construct($options, $groups, $payload);

        $this->min = $min;
        $this->max = $max;
        $this->divisibleBy = $divisibleBy ?? $this->divisibleBy;
        $this->exactMessage = $exactMessage ?? $this->exactMessage;
        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
        $this->divisibleByMessage = $divisibleByMessage ?? $this->divisibleByMessage;

        if (null === $this->min && null === $this->max && null === $this->divisibleBy) {
            throw new MissingOptionsException(\sprintf('Either option "min", "max" or "divisibleBy" must be given for constraint "%s".', __CLASS__), ['min', 'max', 'divisibleBy']);
        }
    }
}
