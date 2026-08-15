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

/**
 * A stream double that models the server responses as a FIFO buffer, so that an
 * unread reply left in the buffer desyncs the following reads (unlike the lockstep
 * DummyStream, whose readLine() always returns the last response).
 *
 * The read of the response to the first "end of DATA" command times out once, while
 * the server reply for that command stays in the buffer, reproducing the pipelining
 * desync.
 */
class TimeoutBufferStream extends AbstractStream
{
    /** @var string[] */
    private array $buffer = [];
    private array $commands = [];
    private bool $closed = true;
    private bool $timeoutArmed = false;
    private bool $timeoutTriggered = false;

    public function initialize(): void
    {
        $this->closed = false;
        $this->buffer = ['220 localhost ESMTP'."\r\n"];
    }

    public function write(string $bytes, $debug = true): void
    {
        if ($this->closed) {
            throw new TransportException('Unable to write bytes on the wire.');
        }

        // Message body chunks are written with $debug === false and get no reply.
        if (!$debug) {
            return;
        }

        $this->commands[] = $bytes;

        if ("\r\n.\r\n" === $bytes) {
            // Server accepted the message; its reply is queued as usual, but the very
            // first time we simulate a timeout while reading it back.
            $this->buffer[] = '250 OK queued as 000501c4054c'."\r\n";
            if (!$this->timeoutTriggered) {
                $this->timeoutTriggered = true;
                $this->timeoutArmed = true;
            }

            return;
        }

        $this->buffer[] = match (true) {
            str_starts_with($bytes, 'HELO'), str_starts_with($bytes, 'EHLO') => '250 localhost'."\r\n",
            str_starts_with($bytes, 'RSET') => '250 2.0.0 Resetting'."\r\n",
            str_starts_with($bytes, 'DATA') => '354 Enter message, ending with "." on a line by itself'."\r\n",
            str_starts_with($bytes, 'QUIT') => '221 Goodbye'."\r\n",
            default => '250 OK'."\r\n",
        };
    }

    public function readLine(): string
    {
        if ($this->timeoutArmed) {
            $this->timeoutArmed = false;

            throw new TransportException('Connection to "localhost" timed out.');
        }

        return array_shift($this->buffer) ?? '';
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

    protected function getReadConnectionDescription(): string
    {
        return 'localhost';
    }

    public function terminate(): void
    {
        parent::terminate();
        $this->closed = true;
        $this->buffer = [];
    }
}
