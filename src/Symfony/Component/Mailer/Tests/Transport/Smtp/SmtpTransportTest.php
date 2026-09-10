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
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\InvalidArgumentException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @group time-sensitive
 */
class SmtpTransportTest extends TestCase
{
    private const CLOCK_TICK_MICROSECONDS = 1;

    public function testToString()
    {
        $t = new SmtpTransport();
        $this->assertEquals('smtps://localhost', (string) $t);

        $t = new SmtpTransport((new SocketStream())->setHost('127.0.0.1')->setPort(2525)->disableTls());
        $this->assertEquals('smtp://127.0.0.1:2525', (string) $t);
    }

    public function testConnectionIsResetAfterTimeoutToPreventResponseDesync()
    {
        $stream = new TimeoutBufferStream();
        $envelope = new Envelope(new Address('sender@example.org'), [new Address('recipient@example.org')]);

        $transport = new SmtpTransport($stream);
        // A failed send resets lastMessageTime to 0; keep the ping path out of the way.
        $transport->setPingThreshold(\PHP_INT_MAX);

        // The read of the first message's end-of-DATA reply times out.
        try {
            $transport->send(new RawMessage('Message 1'), $envelope);
            $this->fail('The first message is expected to time out.');
        } catch (TransportException $e) {
            $this->assertStringContainsString('timed out', $e->getMessage());
        }

        // Before the fix, the timed-out reply stayed buffered and RSET consumed it, so
        // every following command read one response too early: DATA got "250" instead of
        // "354" ("Expected response code 354 but got code 250"). The connection must be
        // closed and re-opened instead, so the next message goes through cleanly.
        $transport->send(new RawMessage('Message 2'), $envelope);

        $heloCommands = 0;
        foreach ($stream->getCommands() as $command) {
            if (str_starts_with($command, 'HELO')) {
                ++$heloCommands;
            }
        }
        $this->assertSame(2, $heloCommands, 'The transport must reconnect after a read timeout.');
    }

    public function testConnectionIsResetAfterServiceUnavailableResponse()
    {
        $stream = new class extends DummyStream {
            private array $responses = [];
            private bool $failNextPing = true;

            public function write(string $bytes, $debug = true): void
            {
                parent::write($bytes, $debug);

                if ($this->failNextPing && "NOOP\r\n" === $bytes) {
                    $this->failNextPing = false;
                    $this->responses = [
                        "250 2.0.0 OK\r\n",
                        "421 4.4.1 Connection timed out.\r\n",
                    ];
                }
            }

            public function readLine(): string
            {
                return array_shift($this->responses) ?? parent::readLine();
            }
        };
        $envelope = new Envelope(new Address('sender@example.org'), [new Address('recipient@example.org')]);

        $transport = new SmtpTransport($stream);
        $transport->setPingThreshold(0);
        $transport->send(new RawMessage('Message 1'), $envelope);
        usleep(self::CLOCK_TICK_MICROSECONDS);

        try {
            $transport->send(new RawMessage('Message 2'), $envelope);
            $this->fail('The second message is expected to receive a service unavailable response.');
        } catch (TransportException $e) {
            $this->assertSame(421, $e->getCode());
        }

        $this->assertTrue($stream->isClosed(), 'The transport must disconnect when the server closes the transmission channel.');

        // Ensure the next send reconnects because the transport was stopped, not
        // because the ping path happened to detect the closed stream.
        $transport->setPingThreshold(\PHP_INT_MAX);
        $transport->send(new RawMessage('Message 3'), $envelope);
        $this->assertFalse($stream->isClosed());
    }

    public function testConnectionIsResetAfterServiceUnavailableGreeting()
    {
        $stream = new class extends DummyStream {
            private bool $failGreeting = true;

            public function readLine(): string
            {
                if ($this->failGreeting) {
                    $this->failGreeting = false;

                    return "421 4.3.2 Service not available\r\n";
                }

                return parent::readLine();
            }
        };
        $transport = new SmtpTransport($stream);

        try {
            $transport->start();
            $this->fail('The server is expected to reject the connection.');
        } catch (TransportException $e) {
            $this->assertSame(421, $e->getCode());
        }

        $this->assertTrue($stream->isClosed(), 'The transport must disconnect after a service unavailable greeting.');
    }

    public function testPingReconnectsWhenTheServerQueuedAnotherReply()
    {
        $stream = new class extends DummyStream {
            private array $responses = [];
            private bool $armed = true;

            public function write(string $bytes, $debug = true): void
            {
                parent::write($bytes, $debug);

                if ($this->armed && "NOOP\r\n" === $bytes) {
                    $this->armed = false;
                    $this->responses = ["250 2.0.0 OK\r\n", "421 4.4.1 Connection timed out.\r\n"];
                }
            }

            public function readLine(): string
            {
                return array_shift($this->responses) ?? parent::readLine();
            }

            public function hasPendingData(): bool
            {
                return (bool) $this->responses;
            }

            public function terminate(): void
            {
                parent::terminate();
                $this->responses = [];
            }
        };
        $envelope = new Envelope(new Address('sender@example.org'), [new Address('recipient@example.org')]);

        $transport = new SmtpTransport($stream);
        $transport->setPingThreshold(-1);
        $transport->send(new RawMessage('Message 1'), $envelope);
        $transport->send(new RawMessage('Message 2'), $envelope);

        $heloCommands = 0;
        foreach ($stream->getCommands() as $command) {
            if (str_starts_with($command, 'HELO')) {
                ++$heloCommands;
            }
        }
        $this->assertSame(2, $heloCommands, 'The transport must reconnect when the server queued another reply.');
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
        usleep(1500000);

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
        $message->addPart(new DataPart(new File('/does_not_exists')));

        try {
            $transport->send($message);
            $this->fail('Expected Symfony\Component\Mime\Exception\InvalidArgumentException to be thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertMatchesRegularExpression('{Path "/does_not_exists"}i', $e->getMessage());
        }

        $this->assertNotContains("\r\n.\r\n", $stream->getCommands());
        $this->assertTrue($stream->isClosed());
    }

    public function testWriteEncodedRecipientAndSenderAddresses()
    {
        $stream = new DummyStream();

        $transport = new SmtpTransport($stream);

        $message = new Email();
        $message->from('sender@exämple.org');
        $message->addTo('recipient@exämple.org');
        $message->addTo('recipient2@example.org');
        $message->text('.');

        $transport->send($message);

        $this->assertContains("MAIL FROM:<sender@xn--exmple-cua.org>\r\n", $stream->getCommands());
        $this->assertContains("RCPT TO:<recipient@xn--exmple-cua.org>\r\n", $stream->getCommands());
        $this->assertContains("RCPT TO:<recipient2@example.org>\r\n", $stream->getCommands());
    }

    /**
     * @dataProvider provideLocalDomainsWithControlCharacters
     */
    public function testSetLocalDomainRejectsControlCharacters(string $domain)
    {
        $transport = new SmtpTransport(new DummyStream());

        $this->expectException(\Symfony\Component\Mailer\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('The local domain name must not contain control characters.');

        $transport->setLocalDomain($domain);
    }

    public static function provideLocalDomainsWithControlCharacters(): iterable
    {
        yield 'CRLF' => ["evil\r\nMAIL FROM:<injected@example.org>"];
        yield 'CR only' => ["example.org\r"];
        yield 'LF only' => ["example.org\n"];
        yield 'NUL byte' => ["example.org\x00"];
        yield 'HTAB' => ["example.org\t"];
        yield 'DEL (0x7F)' => ["example.org\x7F"];
        yield 'control char in the middle' => ["exam\x01ple.org"];
    }

    public function testMessageIdFromServerIsEmbeddedInSentMessageEvent()
    {
        $calls = 0;
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->with($this->callback(static function ($event) use (&$calls): bool {
                ++$calls;

                if (1 === $calls && $event instanceof MessageEvent) {
                    return true;
                }

                if (2 === $calls && $event instanceof SentMessageEvent && '000501c4054c' === $event->getMessage()->getMessageId()) {
                    return true;
                }

                return false;
            }));
        $transport = new SmtpTransport(new DummyStream(), $eventDispatcher);

        $email = new Email();
        $email->from('sender@example.com');
        $email->to('recipient@example.com');
        $email->text('.');

        $transport->send($email);

        $this->assertSame(2, $calls);
    }

    public function testAssertResponseCodeNoCodes()
    {
        $this->expectException(LogicException::class);
        $this->invokeAssertResponseCode('response', []);
    }

    public function testAssertResponseCodeWithEmptyResponse()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Expected response code "220" but got empty code.');
        $this->invokeAssertResponseCode('', [220]);
    }

    public function testAssertResponseCodeWithNotValidCode()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Expected response code "220" but got code "550", with message "550 Access Denied".');
        $this->expectExceptionCode(550);
        $this->invokeAssertResponseCode('550 Access Denied', [220]);
    }

    private function invokeAssertResponseCode(string $response, array $codes): void
    {
        $transport = new SmtpTransport($this->createStub(AbstractStream::class));
        $m = new \ReflectionMethod($transport, 'assertResponseCode');
        $m->invoke($transport, $response, $codes);
    }

    public function testStop()
    {
        $stream = new DummyStream();
        $envelope = new Envelope(new Address('sender@example.org'), [new Address('recipient@example.org')]);

        $transport = new SmtpTransport($stream);
        $transport->send(new RawMessage('Message 1'), $envelope);
        $this->assertFalse($stream->isClosed());

        $transport->stop();
        $this->assertTrue($stream->isClosed());
    }
}
