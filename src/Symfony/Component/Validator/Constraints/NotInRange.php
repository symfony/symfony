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
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class NotInRange extends Constraint
{
    const INVALID_CHARACTERS_ERROR = '88dea871-e57a-4836-9b55-11b7dcd73dc6';
    const IN_RANGE_ERROR = '36b1a3ef-effe-4be4-b153-715c40ebce16';

    protected static $errorNames = [
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::IN_RANGE_ERROR => 'IN_RANGE_ERROR',
    ];

    public $inRangeMessage = 'This value should not be between {{ min }} and {{ max }}.';
    public $invalidMessage = 'This value should be a valid number or a valid datetime.';
    public $min;
    public $minPropertyPath;
    public $max;
    public $maxPropertyPath;

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
        }

        parent::__construct($options);

        if (null === $this->min && null === $this->minPropertyPath) {
            throw new MissingOptionsException(sprintf('Either option "min" or "minPropertyPath" must be given for constraint %s', static::class), ['min', 'minPropertyPath']);
        }

        if (null === $this->max && null === $this->maxPropertyPath) {
            throw new MissingOptionsException(sprintf('Either option "max" or "maxPropertyPath" must be given for constraint %s', static::class), ['max', 'maxPropertyPath']);
        }
    }
}
