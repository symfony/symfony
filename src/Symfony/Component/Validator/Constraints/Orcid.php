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
 * @author Dominic Bordelon <dominicbordelon@gmail.com>
 */
class Orcid extends Constraint
{
    const TOO_SHORT_ERROR = '29424315-1b99-41fc-9b13-b75d1c0a8639';
    const TOO_LONG_ERROR = 'c22ce7be-7160-4b19-97e2-600b34bf5fea';
    const MISSING_HYPHENS_ERROR = '3a2a78ab-9062-4c93-b541-a35b4b9bdf17';
    const INVALID_CHARACTERS_ERROR = 'bdf57e2c-3ad7-4b4d-ad46-a8a255792aea';
    const INVALID_CASE_ERROR = '459fac90-10ac-4fec-9fc1-11f411ee5cd2';
    const CHECKSUM_FAILED_ERROR = '2f5b06bc-9e9d-4de4-b504-631f7ed30ecf';

    protected static $errorNames = array(
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::MISSING_HYPHENS_ERROR => 'MISSING_HYPHEN_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::INVALID_CASE_ERROR => 'INVALID_CASE_ERROR',
        self::CHECKSUM_FAILED_ERROR => 'CHECKSUM_FAILED_ERROR',
    );

    public $message = 'This value is not a valid ORCID.';
    public $caseSensitive = false;
    public $requireHyphens = false;
}
