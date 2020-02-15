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
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Przemysław Bogusz <przemyslaw.bogusz@tubotax.pl>
 */
class RgbColor extends Constraint
{
    public const INVALID_FORMAT_ERROR = '5720b8cb-fb2a-430e-94e9-2d33b4cd1fa9';

    protected static $errorNames = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
    ];

    public $message = 'This is not a valid RGB color.';
    public $allowAlpha = true;
    public $alphaPrecision = 2;
    public $lowerCaseOnly = false;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (!\is_int($this->alphaPrecision) || 1 > $this->alphaPrecision) {
            throw new InvalidArgumentException(sprintf('The "alphaPrecision" option must be a positive integer ("%s" given)', $this->alphaPrecision));
        }
    }
}
