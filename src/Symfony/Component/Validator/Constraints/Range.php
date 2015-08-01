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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Range extends Constraint
{
    const INVALID_CHARACTERS_ERROR = 'ad9a9798-7a99-4df7-8ce9-46e416a1e60b';
    const TOO_HIGH_ERROR = '2d28afcb-e32e-45fb-a815-01c431a86a69';
    const TOO_LOW_ERROR = '76454e69-502c-46c5-9643-f447d837c4d5';

    /**
     * @deprecated Deprecated since version 2.8, to be removed in 3.0. Use
     *             {@link INVALID_CHARACTERS_ERROR} instead.
     */
    const INVALID_VALUE_ERROR = self::INVALID_CHARACTERS_ERROR;

    /**
     * @deprecated Deprecated since version 2.8, to be removed in 3.0. Use
     *             {@link TOO_HIGH_ERROR} instead.
     */
    const BEYOND_RANGE_ERROR = self::TOO_HIGH_ERROR;

    /**
     * @deprecated Deprecated since version 2.8, to be removed in 3.0. Use
     *             {@link TOO_LOW_ERROR} instead.
     */
    const BELOW_RANGE_ERROR = self::TOO_LOW_ERROR;

    protected static $errorNames = array(
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
    );

    public $minMessage = 'This value should be {{ limit }} or more.';
    public $maxMessage = 'This value should be {{ limit }} or less.';
    public $invalidMessage = 'This value should be a valid number.';
    public $min;
    public $max;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (null === $this->min && null === $this->max) {
            throw new MissingOptionsException(sprintf('Either option "min" or "max" must be given for constraint %s', __CLASS__), array('min', 'max'));
        }
    }
}
