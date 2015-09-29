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
 */
class Email extends Constraint
{
    const INVALID_FORMAT_ERROR = 1;
    const MX_CHECK_FAILED_ERROR = 2;
    const HOST_CHECK_FAILED_ERROR = 3;

    protected static $errorNames = array(
        self::INVALID_FORMAT_ERROR => 'STRICT_CHECK_FAILED_ERROR',
        self::MX_CHECK_FAILED_ERROR => 'MX_CHECK_FAILED_ERROR',
        self::HOST_CHECK_FAILED_ERROR => 'HOST_CHECK_FAILED_ERROR',
    );

    public $message = 'This value is not a valid email address.';
    public $checkMX = false;
    public $checkHost = false;
    public $strict;
}
