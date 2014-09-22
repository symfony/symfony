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
 *
 * @author Colin O'Dell <colinodell@gmail.com>
 */
class Uuid extends Constraint
{
    // Possible versions defined by RFC 4122
    const V1_MAC = 1;
    const V2_DCE = 2;
    const V3_MD5 = 3;
    const V4_RANDOM = 4;
    const V5_SHA1 = 5;

    /**
     * Message to display when validation fails
     *
     * @var string
     */
    public $message = 'This is not a valid UUID.';

    /**
     * Strict mode only allows UUIDs that meet the formal definition and formatting per RFC 4122
     *
     * Set this to `false` to allow legacy formats with different dash positioning or wrapping characters
     *
     * @var bool
     */
    public $strict = true;

    /**
     * Array of allowed versions (see version constants above)
     *
     * All UUID versions are allowed by default
     *
     * @var int[]
     */
    public $versions = array(
        self::V1_MAC,
        self::V2_DCE,
        self::V3_MD5,
        self::V4_RANDOM,
        self::V5_SHA1,
    );
}
