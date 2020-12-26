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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\RawMessage;

class SmtpTransportTest extends TestCase
{
    public function testToString()
    {
        $t = new SmtpTransport();
        $this->assertEquals('smtps://localhost', (string) $t);

        $t = new SmtpTransport((new SocketStream())->setHost('127.0.0.1')->setPort(2525)->disableTls());
        $this->assertEquals('smtp://127.0.0.1:2525', (string) $t);
    }

    public function testSendDoesNotPingBelowThreshold()
    {
        $stream = new DummyStream();
        $envelope = new Envelope(new Address('sender@example.org'), [new Address('recipient@example.org')]);

        $transport = new SmtpTransport($stream);
        $transport->send(new RawMessage('Message 1'), $envelope);
        $transport->send(new RawMessage('Message 2'), $envelope);
        $transport->send(new RawMessage('Message 3'), $envelope);

        $this->assertNotContains("NOOP\r\n", $stream->getCommands());
    }

    public function testSendPingAfterTransportException()
    {
        $stream = new DummyStream();
        $envelope = new Envelope(new Address('sender@example.org'), [new Address('recipient@example.org')]);

        $transport = new SmtpTransport($stream);
        $transport->send(new RawMessage('Message 1'), $envelope);
        $stream->close();
        $catch = false;

        try {
            $transport->send(new RawMessage('Message 2'), $envelope);
        } catch (TransportException $exception) {
            $catch = true;
        }
        $this->assertTrue($catch);
        $this->assertTrue($stream->isClosed());

        $transport->send(new RawMessage('Message 3'), $envelope);

        $this->assertFalse($stream->isClosed());
    }

    public function testSendDoesPingAboveThreshold()
    {
        $stream = new DummyStream();
        $envelope = new Envelope(new Address('sender@example.org'), [new Address('recipient@example.org')]);

        $transport = new SmtpTransport($stream);
        $transport->setPingThreshold(1);

        $transport->send(new RawMessage('Message 1'), $envelope);
        $transport->send(new RawMessage('Message 2'), $envelope);

        $this->assertNotContains("NOOP\r\n", $stream->getCommands());

        $stream->clearCommands();
        sleep(1);

        $transport->send(new RawMessage('Message 3'), $envelope);
        $this->assertContains("NOOP\r\n", $stream->getCommands());
    }

    public function testSendInvalidMessage()
    {
        $stream = new DummyStream();

        $transport = new SmtpTransport($stream);
        $transport->setPingThreshold(1);

        $message = new Email();
        $message->to('recipient@example.org');
        $message->from('sender@example.org');
        $message->attachFromPath('/does_not_exists');

        try {
            $transport->send($message);
            $this->fail('Expected Symfony\Component\Mime\Exception\InvalidArgumentException to be thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('{Path "/does_not_exists"}i', $e->getMessage());
        }

        $this->assertNotContains("\r\n.\r\n", $stream->getCommands());
        $this->assertTrue($stream->isClosed());
    }
}

class DummyStream extends AbstractStream
{
    /**
     * @var string
     */
    private $nextResponse;

    /**
     * @var string[]
     */
    private $commands;

    /**
     * @var bool
     */
    private $closed = true;

    public function initialize(): void
    {
        $this->closed = false;
        $this->nextResponse = '220 localhost';
    }

    public function write(string $bytes, $debug = true): void
    {
        if ($this->closed) {
            throw new TransportException('Unable to write bytes on the wire.');
        }

        $this->commands[] = $bytes;

        if (0 === strpos($bytes, 'DATA')) {
            $this->nextResponse = '354 Enter message, ending with "." on a line by itself';
        } elseif (0 === strpos($bytes, 'QUIT')) {
            $this->nextResponse = '221 Goodbye';
        } else {
            $this->nextResponse = '250 OK';
        }
    }

    public function readLine(): string
    {
        return $this->nextResponse;
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
