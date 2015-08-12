<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Michael Hirschler <michael.vhirsch@gmail.com>
 *
 * @api
 */
class Bic extends Constraint
{
    const INVALID_LENGTH_ERROR = 1;
    const INVALID_CHARACTERS_ERROR = 2;
    const INVALID_BANK_CODE_ERROR = 3;
    const INVALID_COUNTRY_CODE_ERROR = 4;
    const INVALID_CASE_ERROR = 5;

    protected static $errorNames = array(
        self::INVALID_LENGTH_ERROR => 'INVALID_LENGTH_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::INVALID_BANK_CODE_ERROR => 'INVALID_BANK_CODE_ERROR',
        self::INVALID_COUNTRY_CODE_ERROR => 'INVALID_COUNTRY_CODE_ERROR',
        self::INVALID_CASE_ERROR => 'INVALID_CASE_ERROR',
    );

    public $message = 'This is not a valid Business Identifier Codes (BIC).';
}
