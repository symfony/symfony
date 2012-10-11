<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Exception;

use Symfony\Component\Routing\RequestAcceptance;

/**
 * The resource was found but the Accept-* header does not match requirement.
 *
 * This exception should trigger an HTTP 406 response in your application code.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class NotAcceptableException extends \RuntimeException implements ExceptionInterface
{
    protected $acceptance;
    protected $requirement;

    public function __construct(RequestAcceptance $acceptance, $requirement, $message = null, $code = 0, \Exception $previous = null)
    {
        $this->acceptance = $acceptance;
        $this->requirement = $requirement;
        $message = sprintf('None of the accepted values "%s" match route requirement "%s".', implode(', ', $acceptance->getValues()), $requirement);
        parent::__construct($message, $code, $previous);
    }
}
