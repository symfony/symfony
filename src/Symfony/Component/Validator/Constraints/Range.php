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

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Validates that a given number or DateTime object is between some minimum and maximum.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Range extends Constraint
{
    public const INVALID_CHARACTERS_ERROR = 'ad9a9798-7a99-4df7-8ce9-46e416a1e60b';
    public const NOT_IN_RANGE_ERROR = '04b91c99-a946-4221-afc5-e65ebac401eb';
    public const TOO_HIGH_ERROR = '2d28afcb-e32e-45fb-a815-01c431a86a69';
    public const TOO_LOW_ERROR = '76454e69-502c-46c5-9643-f447d837c4d5';

    protected const ERROR_NAMES = [
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::NOT_IN_RANGE_ERROR => 'NOT_IN_RANGE_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
    ];

    public string $notInRangeMessage = 'This value should be between {{ min }} and {{ max }}.';
    public string $minMessage = 'This value should be {{ limit }} or more.';
    public string $maxMessage = 'This value should be {{ limit }} or less.';
    public string $invalidMessage = 'This value should be a valid number.';
    public string $invalidDateTimeMessage = 'This value should be a valid datetime.';
    public mixed $min = null;
    public ?string $minPropertyPath = null;
    public mixed $max = null;
    public ?string $maxPropertyPath = null;

    /**
     * @param array<string,mixed>|null        $options
     * @param string|null                     $invalidMessage         The message if min and max values are numeric but the given value is not
     * @param string|null                     $invalidDateTimeMessage The message if min and max values are PHP datetimes but the given value is not
     * @param int|float|non-empty-string|null $min                    The minimum value, either numeric or a datetime string representation
     * @param non-empty-string|null           $minPropertyPath        Property path to the min value
     * @param int|float|non-empty-string|null $max                    The maximum value, either numeric or a datetime string representation
     * @param non-empty-string|null           $maxPropertyPath        Property path to the max value
     * @param string[]|null                   $groups
     */
    #[HasNamedArguments]
    public function __construct(
        ?array $options = null,
        ?string $notInRangeMessage = null,
        ?string $minMessage = null,
        ?string $maxMessage = null,
        ?string $invalidMessage = null,
        ?string $invalidDateTimeMessage = null,
        mixed $min = null,
        ?string $minPropertyPath = null,
        mixed $max = null,
        ?string $maxPropertyPath = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options, $groups, $payload);

        $this->notInRangeMessage = $notInRangeMessage ?? $this->notInRangeMessage;
        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
        $this->invalidMessage = $invalidMessage ?? $this->invalidMessage;
        $this->invalidDateTimeMessage = $invalidDateTimeMessage ?? $this->invalidDateTimeMessage;
        $this->min = $min ?? $this->min;
        $this->minPropertyPath = $minPropertyPath ?? $this->minPropertyPath;
        $this->max = $max ?? $this->max;
        $this->maxPropertyPath = $maxPropertyPath ?? $this->maxPropertyPath;

        if (null === $this->min && null === $this->minPropertyPath && null === $this->max && null === $this->maxPropertyPath) {
            throw new MissingOptionsException(\sprintf('Either option "min", "minPropertyPath", "max" or "maxPropertyPath" must be given for constraint "%s".', __CLASS__), ['min', 'minPropertyPath', 'max', 'maxPropertyPath']);
        }

        if (null !== $this->min && null !== $this->minPropertyPath) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires only one of the "min" or "minPropertyPath" options to be set, not both.', static::class));
        }

        if (null !== $this->max && null !== $this->maxPropertyPath) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires only one of the "max" or "maxPropertyPath" options to be set, not both.', static::class));
        }

        if ((null !== $this->minPropertyPath || null !== $this->maxPropertyPath) && !class_exists(PropertyAccess::class)) {
            throw new LogicException(\sprintf('The "%s" constraint requires the Symfony PropertyAccess component to use the "minPropertyPath" or "maxPropertyPath" option. Try running "composer require symfony/property-access".', static::class));
        }

        if (null !== $this->min && null !== $this->max && ($minMessage || $maxMessage || isset($options['minMessage']) || isset($options['maxMessage']))) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint can not use "minMessage" and "maxMessage" when the "min" and "max" options are both set. Use "notInRangeMessage" instead.', static::class));
        }
    }
}
