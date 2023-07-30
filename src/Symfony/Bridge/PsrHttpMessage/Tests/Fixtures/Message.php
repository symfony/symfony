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
    public function __construct(
        private readonly string $version = '1.1',
        private array $headers = [],
        private readonly StreamInterface $body = new Stream(),
    ) {
    }

    public function getProtocolVersion(): string
    {
        return $this->version;
    }

    public function withProtocolVersion($version): never
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

    public function withHeader($name, $value): static
    {
        $this->headers[$name] = (array) $value;

        return $this;
    }

    public function withAddedHeader($name, $value): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function withoutHeader($name): static
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
     */
    public function withBody(StreamInterface $body): never
    {
        throw new \BadMethodCallException('Not implemented.');
    }
}
