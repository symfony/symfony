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
    private $statusCode;
    private $headers;

    /**
     * Constructor.
     *
     * @param integer    $statusCode The HTTP status code
     * @param string     $message    The internal exception message
     * @param \Exception $previous   The previous exception
     * @param array      $headers    An array of HTTP headers
     */
    public function __construct($statusCode, $message = null, \Exception $previous = null, array $headers = array())
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, 0, $previous);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }
}
