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
 * MethodNotAllowedHttpException.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class MethodNotAllowedHttpException extends HttpException
{
    /**
     * Constructor.
     *
     * WARNING: The status message will be sent as a response header
     * regardless of debug mode.
     *
     * @param array     $allow         An array of allowed methods
     * @param string    $statusMessage The HTTP response status message
     * @param string    $message       The internal exception message
     * @param integer   $code          The internal exception code
     * @param Exception $previous      The previous exception
     */
    public function __construct(array $allow, $statusMessage = 'Method Not Allowed', $message = null, $code = 0, \Exception $previous = null)
    {
        $headers = array('Allow' => strtoupper(implode(', ', $allow)));

        parent::__construct(405, $statusMessage, $headers, $message ?: $statusMessage, $code, $previous);
    }
}
