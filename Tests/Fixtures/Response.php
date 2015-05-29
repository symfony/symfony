<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Response extends Message implements ResponseInterface
{
    private $statusCode;

    public function __construct($version = '1.1', array $headers = array(), StreamInterface $body = null, $statusCode = 200)
    {
        parent::__construct($version, $headers, $body);

        $this->statusCode = $statusCode;
    }

    public function getStatusCode()
    {
        return $this->statusCode;
    }

    public function withStatus($code, $reasonPhrase = '')
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getReasonPhrase()
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
