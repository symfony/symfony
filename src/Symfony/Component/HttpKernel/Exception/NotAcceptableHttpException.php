<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

/**
 * NotAcceptableHttpException.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class NotAcceptableHttpException extends HttpException
{
    /**
     * Constructor.
     *
     * @param array      $headers  An array of headers
     * @param string     $message  The internal exception message
     * @param \Exception $previous The previous exception
     * @param integer    $code     The internal exception code
     */
    public function __construct($message = null, \Exception $previous = null, array $headers = array(), $code = 0)
    {
        parent::__construct(406, $message, $previous, $headers, $code);
    }
}
