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
 *
 * @api
 */
class Url extends Constraint
{
    public $message = 'This value is not a valid URL.';

    /**
     * A list of scheme's to allow.
     *
     * @var string[]
     */
    public $protocols = array('http', 'https');

    public $bc = true;

    public $allowUserInfo = true;

    /**
     * A list of host types to allow.
     * These can be:
     * - ipv4
     * - ipv6
     * - ipfuture
     * - rfc2396
     * - rfc1034
     * - rfc3986
     *
     * @var string[]
     */
    public $hostTypes = array('ipv4', 'ipv6', 'idna');

    public $allowPort = true;
    public $allowPath = true;
    public $allowQuery = true;
    public $allowFragment = true;
}
