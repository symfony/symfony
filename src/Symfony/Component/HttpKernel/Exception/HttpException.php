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
 * HttpException.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class HttpException extends \RuntimeException implements HttpExceptionInterface
{
    protected $statusCode;
    protected $statusMessage;
    protected $headers;

    public function __construct($statusCode, $statusMessage, array $headers = array(), $message = null, $code = 0, \Exception $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->statusMessage = $statusMessage;
        $this->headers = $headers;

        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function getStatusMessage()
    {
        return $this->statusMessage;
    }

    public function getHeaders()
    {
        return $this->headers;
    }
}
