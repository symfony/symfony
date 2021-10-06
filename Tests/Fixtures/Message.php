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
    private $headers = [];
    private $body;

    public function __construct($version = '1.1', array $headers = [], StreamInterface $body = null)
    {
        $this->version = $version;
        $this->headers = $headers;
        $this->body = $body ?? new Stream();
    }

    public function getProtocolVersion(): string
    {
        return $this->version;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withProtocolVersion($version)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader($name): bool
    {
        return isset($this->headers[$name]);
    }

    public function getHeader($name): array
    {
        return $this->hasHeader($name) ? $this->headers[$name] : [];
    }

    public function getHeaderLine($name): string
    {
        return $this->hasHeader($name) ? implode(',', $this->headers[$name]) : '';
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withHeader($name, $value)
    {
        $this->headers[$name] = (array) $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withAddedHeader($name, $value)
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withoutHeader($name)
    {
        unset($this->headers[$name]);

        return $this;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     *
     * @return static
     */
    public function withBody(StreamInterface $body)
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
