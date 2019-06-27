<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport\Smtp\Stream;

use Symfony\Component\Mailer\Exception\TransportException;

/**
 * A stream supporting remote sockets.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Chris Corbyn
 *
 * @internal
 *
 * @experimental in 4.3
 */
final class SocketStream extends AbstractStream
{
    private $url;
    private $host = 'localhost';
    private $protocol = 'tcp';
    private $port = 25;
    private $timeout = 15;
    private $tls = false;
    private $sourceIp;
    private $streamContextOptions = [];

    public function setTimeout(float $timeout): self
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Literal IPv6 addresses should be wrapped in square brackets.
     */
    public function setHost(string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setPort(int $port): self
    {
        $this->port = $port;

        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Sets the encryption type (tls or ssl).
     */
    public function setEncryption(string $encryption): self
    {
        $encryption = strtolower($encryption);
        if ('tls' === $encryption) {
            $this->protocol = 'tcp';
            $this->tls = true;
        } else {
            $this->protocol = $encryption;
            $this->tls = false;
        }

        return $this;
    }

    public function isTLS(): bool
    {
        return $this->tls;
    }

    public function setStreamOptions(array $options): self
    {
        $this->streamContextOptions = $options;

        return $this;
    }

    public function getStreamOptions(): array
    {
        return $this->streamContextOptions;
    }

    /**
     * Sets the source IP.
     *
     * IPv6 addresses should be wrapped in square brackets.
     */
    public function setSourceIp(string $ip): self
    {
        $this->sourceIp = $ip;

        return $this;
    }

    /**
     * Returns the IP used to connect to the destination.
     */
    public function getSourceIp(): ?string
    {
        return $this->sourceIp;
    }

    public function initialize(): void
    {
        $this->url = $this->host.':'.$this->port;
        if ($this->protocol) {
            $this->url = $this->protocol.'://'.$this->url;
        }
        $options = [];
        if ($this->sourceIp) {
            $options['socket']['bindto'] = $this->sourceIp.':0';
        }
        if ($this->streamContextOptions) {
            $options = array_merge($options, $this->streamContextOptions);
        }
        if ($this->isTLS()) {
            $options['ssl']['crypto_method'] = $options['ssl']['crypto_method'] ?? STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        $streamContext = stream_context_create($options);

        set_error_handler(function ($type, $msg) {
            throw new TransportException(sprintf('Connection could not be established with host "%s": %s.', $this->url, $msg));
        });
        try {
            $this->stream = stream_socket_client($this->url, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $streamContext);
        } finally {
            restore_error_handler();
        }

        stream_set_blocking($this->stream, true);
        stream_set_timeout($this->stream, $this->timeout);
        $this->in = &$this->stream;
        $this->out = &$this->stream;
    }

    public function startTLS(): bool
    {
        return (bool) stream_socket_enable_crypto($this->stream, true);
    }

    public function terminate(): void
    {
        if (null !== $this->stream) {
            fclose($this->stream);
        }

        parent::terminate();
    }

    protected function getReadConnectionDescription(): string
    {
        return $this->url;
    }
}
