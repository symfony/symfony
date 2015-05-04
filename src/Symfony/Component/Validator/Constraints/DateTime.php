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

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Radu Murzea <radu.murzea@gmail.com>
 *
 * @api
 */
class DateTime extends Constraint
{
    const INVALID_FORMAT_ERROR = 1;
    const INVALID_DATE_ERROR = 2;
    const INVALID_TIME_ERROR = 3;

    protected static $errorNames = array(
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::INVALID_DATE_ERROR => 'INVALID_DATE_ERROR',
        self::INVALID_TIME_ERROR => 'INVALID_TIME_ERROR',
    );

    public $message = 'This value is not a valid datetime.';
    public $format = 'Y-m-d H:i:s';
}
