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
    public $min;
    public $minPropertyPath;
    public $max;
    public $maxPropertyPath;

    // BC layer, to be removed in 5.0
    /**
     * @internal
     */
    public $deprecatedMinMessageSet = false;

    /**
     * @internal
     */
    public $deprecatedMaxMessageSet = false;

    public function __construct($options = null)
    {
        if (\is_array($options)) {
            if (isset($options['min']) && isset($options['minPropertyPath'])) {
                throw new ConstraintDefinitionException(sprintf('The "%s" constraint requires only one of the "min" or "minPropertyPath" options to be set, not both.', static::class));
            }

            if (isset($options['max']) && isset($options['maxPropertyPath'])) {
                throw new ConstraintDefinitionException(sprintf('The "%s" constraint requires only one of the "max" or "maxPropertyPath" options to be set, not both.', static::class));
            }

            if ((isset($options['minPropertyPath']) || isset($options['maxPropertyPath'])) && !class_exists(PropertyAccess::class)) {
                throw new LogicException(sprintf('The "%s" constraint requires the Symfony PropertyAccess component to use the "minPropertyPath" or "maxPropertyPath" option.', static::class));
            }

            if (isset($options['min']) && isset($options['max'])) {
                $this->deprecatedMinMessageSet = isset($options['minMessage']);
                $this->deprecatedMaxMessageSet = isset($options['maxMessage']);

                if ($this->deprecatedMinMessageSet || $this->deprecatedMaxMessageSet) {
                    @trigger_error('Since symfony/validator 4.4: "minMessage" and "maxMessage" are deprecated when the "min" and "max" options are both set. Use "notInRangeMessage" instead.', \E_USER_DEPRECATED);
                }
            }
        }

        parent::__construct($options);

        if (null === $this->min && null === $this->minPropertyPath && null === $this->max && null === $this->maxPropertyPath) {
            throw new MissingOptionsException(sprintf('Either option "min", "minPropertyPath", "max" or "maxPropertyPath" must be given for constraint "%s".', __CLASS__), ['min', 'minPropertyPath', 'max', 'maxPropertyPath']);
        }
    }
}
