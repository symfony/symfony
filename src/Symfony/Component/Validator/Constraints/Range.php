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
use Symfony\Component\PropertyAccess\PropertyPathInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Range extends Constraint
{
    const INVALID_CHARACTERS_ERROR = 'ad9a9798-7a99-4df7-8ce9-46e416a1e60b';
    const NOT_IN_RANGE_ERROR = '04b91c99-a946-4221-afc5-e65ebac401eb';
    const TOO_HIGH_ERROR = '2d28afcb-e32e-45fb-a815-01c431a86a69';
    const TOO_LOW_ERROR = '76454e69-502c-46c5-9643-f447d837c4d5';

    protected static $errorNames = [
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::NOT_IN_RANGE_ERROR => 'NOT_IN_RANGE_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
    ];

    public $notInRangeMessage = 'This value should be between {{ min }} and {{ max }}.';
    public $minMessage = 'This value should be {{ limit }} or more.';
    public $maxMessage = 'This value should be {{ limit }} or less.';
    public $invalidMessage = 'This value should be a valid number.';
    public $invalidDateTimeMessage = 'This value should be a valid datetime.';
    public $min;
    public $minPropertyPath;
    public $max;
    public $maxPropertyPath;

    /**
     * @internal
     */
    public $deprecatedMinMessageSet = false;

    /**
     * @internal
     */
    public $deprecatedMaxMessageSet = false;

    /**
     * {@inheritdoc}
     *
     * @param string|PropertyPathInterface|null $minPropertyPath
     * @param string|PropertyPathInterface|null $maxPropertyPath
     */
    public function __construct(
        array $options = null,
        string $notInRangeMessage = null,
        string $minMessage = null,
        string $maxMessage = null,
        string $invalidMessage = null,
        string $invalidDateTimeMessage = null,
        $min = null,
        $minPropertyPath = null,
        $max = null,
        $maxPropertyPath = null,
        array $groups = null,
        $payload = null
    ) {
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
            throw new MissingOptionsException(sprintf('Either option "min", "minPropertyPath", "max" or "maxPropertyPath" must be given for constraint "%s".', __CLASS__), ['min', 'minPropertyPath', 'max', 'maxPropertyPath']);
        }

        if (null !== $this->min && null !== $this->minPropertyPath) {
            throw new ConstraintDefinitionException(sprintf('The "%s" constraint requires only one of the "min" or "minPropertyPath" options to be set, not both.', static::class));
        }

        if (null !== $this->max && null !== $this->maxPropertyPath) {
            throw new ConstraintDefinitionException(sprintf('The "%s" constraint requires only one of the "max" or "maxPropertyPath" options to be set, not both.', static::class));
        }

        if ((null !== $this->minPropertyPath || null !== $this->maxPropertyPath) && !class_exists(PropertyAccess::class)) {
            throw new LogicException(sprintf('The "%s" constraint requires the Symfony PropertyAccess component to use the "minPropertyPath" or "maxPropertyPath" option.', static::class));
        }

        if (null !== $this->min && null !== $this->max) {
            $this->deprecatedMinMessageSet = isset($options['minMessage']) || null !== $minMessage;
            $this->deprecatedMaxMessageSet = isset($options['maxMessage']) || null !== $maxMessage;

            // BC layer, should throw a ConstraintDefinitionException in 6.0
            if ($this->deprecatedMinMessageSet || $this->deprecatedMaxMessageSet) {
                trigger_deprecation('symfony/validator', '4.4', '"minMessage" and "maxMessage" are deprecated when the "min" and "max" options are both set. Use "notInRangeMessage" instead.');
            }
        }
    }
}
