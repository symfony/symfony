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
 * @author The Whole Life To Learn <thewholelifetolearn@gmail.com>
 * @author Manuel Reinhard <manu@sprain.ch>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Isbn extends Constraint
{
    const TOO_SHORT_ERROR = 1;
    const TOO_LONG_ERROR = 2;
    const INVALID_CHARACTERS_ERROR = 3;
    const CHECKSUM_FAILED_ERROR = 4;
    const TYPE_NOT_RECOGNIZED_ERROR = 5;

    protected static $errorNames = array(
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::CHECKSUM_FAILED_ERROR => 'CHECKSUM_FAILED_ERROR',
        self::TYPE_NOT_RECOGNIZED_ERROR => 'TYPE_NOT_RECOGNIZED_ERROR',
    );

    public $isbn10Message = 'This value is not a valid ISBN-10.';
    public $isbn13Message = 'This value is not a valid ISBN-13.';
    public $bothIsbnMessage = 'This value is neither a valid ISBN-10 nor a valid ISBN-13.';
    public $type;
    public $message;

    /**
     * @deprecated since version 2.5, to be removed in 3.0. Use option "type" instead.
     *
     * @var bool
     */
    public $isbn10 = false;

    /**
     * @deprecated since version 2.5, to be removed in 3.0. Use option "type" instead.
     *
     * @var bool
     */
    public $isbn13 = false;

    /**
     * {@inheritdoc}
     */
    public function getDefaultOption()
    {
        return 'type';
    }
}
