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
 * @author George Mponos <gmponos@gmail.com>
 */
class BitcoinAddress extends Constraint
{
    const TOO_SHORT_ERROR = '';
    const TOO_LONG_ERROR = '';
    const TYPE_NOT_RECOGNIZED_ERROR = '';

    protected static $errorNames = array(
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::TYPE_NOT_RECOGNIZED_ERROR => 'TYPE_NOT_RECOGNIZED_ERROR',
    );

    public $type;
    public $message = 'The {{ value }} in not a valid {{ type }} wallet address.';
}