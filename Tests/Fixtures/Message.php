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

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Message.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Message implements MessageInterface
{
    private $version = '1.1';
    private $headers = array();
    private $body;

    public function __construct($version = '1.1', array $headers = array(), StreamInterface $body = null)
    {
        $this->version = $version;
        $this->headers = $headers;
        $this->body = null === $body ? new Stream() : $body;
    }

    public function getProtocolVersion()
    {
        return $this->version;
    }

    public function withProtocolVersion($version)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function hasHeader($name)
    {
        return isset($this->headers[$name]);
    }

    public function getHeader($name)
    {
        return $this->hasHeader($name) ? $this->headers[$name] : array();
    }

    public function getHeaderLine($name)
    {
        return $this->hasHeader($name) ? implode(',', $this->headers[$name]) : '';
    }

    public function withHeader($name, $value)
    {
        $this->headers[$name] = (array) $value;

        return $this;
    }

    public function withAddedHeader($name, $value)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withoutHeader($name)
    {
        unset($this->headers[$name]);

        return $this;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body)
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
