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
class Url extends Constraint
{
    public $message = 'This value is not a valid URL.';
    public $dnsMessage = 'The host could not be resolved.';
    public $failMessage = 'The URL response has a non valid http code';    
    public $protocols = array('http', 'https');
    public $checkDNS = false;
    public $checkStatusCode = false;
    public $validCodes = array(200);
}
