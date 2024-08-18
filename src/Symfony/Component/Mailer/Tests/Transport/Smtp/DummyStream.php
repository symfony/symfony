<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport\Smtp;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;

class DummyStream extends AbstractStream
{
    private string $nextResponse;
    private array $commands = [];
    private bool $closed = true;

    public function initialize(): void
    {
        $this->closed = false;
        $this->nextResponse = '220 localhost ESMTP';
    }

    public function disableTls(): static
    {
        return $this;
    }

    public function isTLS(): bool
    {
        return false;
    }

    public function setHost(string $host): static
    {
        return $this;
    }

    public function setPort(int $port): static
    {
        return $this;
    }

    public function write(string $bytes, $debug = true): void
    {
        if ($this->closed) {
            throw new TransportException('Unable to write bytes on the wire.');
        }

        $this->commands[] = $bytes;

        if (str_starts_with($bytes, 'EHLO')) {
            $this->nextResponse = '250 localhost'."\r\n".'250-AUTH PLAIN LOGIN CRAM-MD5 XOAUTH2';
        } elseif (str_starts_with($bytes, 'AUTH LOGIN')) {
            $this->nextResponse = '334 VXNlcm5hbWU6';
        } elseif (str_starts_with($bytes, 'dGVzdHVzZXI=')) {
            $this->nextResponse = '334 UGFzc3dvcmQ6';
        } elseif (str_starts_with($bytes, 'cDRzc3cwcmQ=')) {
            $this->nextResponse = '535 5.7.139 Authentication unsuccessful';
        } elseif (str_starts_with($bytes, 'dGltZWRvdXQ=')) {
            throw new TransportException('Connection to "localhost" timed out.');
        } elseif (str_starts_with($bytes, 'AUTH CRAM-MD5')) {
            $this->nextResponse = '334 PDAxMjM0NTY3ODkuMDEyMzQ1NjdAc3ltZm9ueT4=';
        } elseif (str_starts_with($bytes, 'dGVzdHVzZXIgNTdlYzg2ODM5OWZhZThjY2M5OWFhZGVjZjhiZTAwNmY=')) {
            $this->nextResponse = '535 5.7.139 Authentication unsuccessful';
        } elseif (str_starts_with($bytes, 'AUTH PLAIN') || str_starts_with($bytes, 'AUTH XOAUTH2')) {
            $this->nextResponse = '535 5.7.139 Authentication unsuccessful';
        } elseif (str_starts_with($bytes, 'RSET')) {
            $this->nextResponse = '250 2.0.0 Resetting';
        } elseif (str_starts_with($bytes, 'DATA')) {
            $this->nextResponse = '354 Enter message, ending with "." on a line by itself';
        } elseif (str_starts_with($bytes, 'QUIT')) {
            $this->nextResponse = '221 Goodbye';
        } else {
            $this->nextResponse = '250 OK queued as 000501c4054c';
        }
    }

    public function readLine(): string
    {
        return $this->nextResponse."\r\n";
    }

    public function flush(): void
    {
    }

    /**
     * @return string[]
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    public function clearCommands(): void
    {
        $this->commands = [];
    }

    protected function getReadConnectionDescription(): string
    {
        return 'null';
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function terminate(): void
    {
        parent::terminate();
        $this->closed = true;
    }
}
