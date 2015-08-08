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
 * @author Manuel Reinhard <manu@sprain.ch>
 * @author Michael Schummel
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Iban extends Constraint
{
    /** @deprecated, to be removed in 3.0. */
    const TOO_SHORT_ERROR = 1;
    const INVALID_COUNTRY_CODE_ERROR = 2;
    const INVALID_CHARACTERS_ERROR = 3;
    /** @deprecated, to be removed in 3.0. */
    const INVALID_CASE_ERROR = 4;
    const CHECKSUM_FAILED_ERROR = 5;
    const INVALID_FORMAT_ERROR = 6;
    const NOT_SUPPORTED_COUNTRY_CODE_ERROR = 7;

    protected static $errorNames = array(
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::INVALID_COUNTRY_CODE_ERROR => 'INVALID_COUNTRY_CODE_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::INVALID_CASE_ERROR => 'INVALID_CASE_ERROR',
        self::CHECKSUM_FAILED_ERROR => 'CHECKSUM_FAILED_ERROR',
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::NOT_SUPPORTED_COUNTRY_CODE_ERROR => 'NOT_SUPPORTED_COUNTRY_CODE_ERROR',
    );

    public $message = 'This is not a valid International Bank Account Number (IBAN).';
}
