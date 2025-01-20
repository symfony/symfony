<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\DelayedEnvelope;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\ProcessStream;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class SendmailTransportTest extends TestCase
{
    private const FAKE_SENDMAIL = __DIR__.'/Fixtures/fake-sendmail.php -t';
    private const FAKE_FAILING_SENDMAIL = __DIR__.'/Fixtures/fake-failing-sendmail.php -t';
    private const FAKE_INTERACTIVE_SENDMAIL = __DIR__.'/Fixtures/fake-failing-sendmail.php -bs';

    private string $argsPath;

    protected function setUp(): void
    {
        $this->argsPath = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'sendmail_args';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->argsPath)) {
            @unlink($this->argsPath);
        }
        unset($this->argsPath);
    }

    public function testToString()
    {
        $t = new SendmailTransport();
        $this->assertEquals('smtp://sendmail', (string) $t);
    }

    public function testToIsUsedWhenRecipientsAreNotSet()
    {
        $this->skipOnWindows();

        $mail = new Email();
        $mail
            ->from('from@mail.com')
            ->to('to@mail.com')
            ->subject('Subject')
            ->text('Some text')
        ;

        $envelope = new DelayedEnvelope($mail);

        $sendmailTransport = new SendmailTransport(self::FAKE_SENDMAIL);
        $sendmailTransport->send($mail, $envelope);

        $this->assertStringEqualsFile($this->argsPath, __DIR__.'/Fixtures/fake-sendmail.php -ffrom@mail.com to@mail.com');
    }

    public function testRecipientsAreUsedWhenSet()
    {
        $this->skipOnWindows();

        [$mail, $envelope] = $this->defaultMailAndEnvelope();

        $sendmailTransport = new SendmailTransport(self::FAKE_SENDMAIL);
        $sendmailTransport->send($mail, $envelope);

        $this->assertStringEqualsFile($this->argsPath, __DIR__.'/Fixtures/fake-sendmail.php -ffrom@mail.com recipient@mail.com');
    }

    public function testThrowsTransportExceptionOnFailure()
    {
        $this->skipOnWindows();

        [$mail, $envelope] = $this->defaultMailAndEnvelope();

        $sendmailTransport = new SendmailTransport(self::FAKE_FAILING_SENDMAIL);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Process failed with exit code 42: Sending failed');
        $sendmailTransport->send($mail, $envelope);

        $streamProperty = new \ReflectionProperty(SendmailTransport::class, 'stream');
        $stream = $streamProperty->getValue($sendmailTransport);

        $this->assertNull($stream->stream);
    }

    public function testStreamIsClearedOnFailure()
    {
        $this->skipOnWindows();

        [$mail, $envelope] = $this->defaultMailAndEnvelope();

        $sendmailTransport = new SendmailTransport(self::FAKE_FAILING_SENDMAIL);
        try {
            $sendmailTransport->send($mail, $envelope);
        } catch (TransportException $e) {
        }

        $streamProperty = new \ReflectionProperty(SendmailTransport::class, 'stream');
        $stream = $streamProperty->getValue($sendmailTransport);
        $innerStreamProperty = new \ReflectionProperty(ProcessStream::class, 'stream');

        $this->assertNull($innerStreamProperty->getValue($stream));
    }

    public function testDoesNotThrowWhenInteractive()
    {
        $this->skipOnWindows();

        [$mail, $envelope] = $this->defaultMailAndEnvelope();

        $sendmailTransport = new SendmailTransport(self::FAKE_INTERACTIVE_SENDMAIL);
        $transportProperty = new \ReflectionProperty(SendmailTransport::class, 'transport');

        // Replace the transport with an anonymous consumer that trigger the stream methods
        $transportProperty->setValue($sendmailTransport, new class($transportProperty->getValue($sendmailTransport)->getStream()) extends SmtpTransport {
            private $stream;

            public function __construct(ProcessStream $stream)
            {
                $this->stream = $stream;
            }

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->stream->initialize();
                $this->stream->write('SMTP');
                $this->stream->terminate();

                return new SentMessage($message, $envelope);
            }

            public function __toString(): string
            {
                return 'Interactive mode test';
            }
        });

        $sendmailTransport->send($mail, $envelope);

        $this->assertStringEqualsFile($this->argsPath, __DIR__.'/Fixtures/fake-failing-sendmail.php -bs');
    }

    private function skipOnWindows()
    {
        if ('\\' === \DIRECTORY_SEPARATOR) {
            $this->markTestSkipped('Windows does not support shebangs nor non-blocking standard streams');
        }
    }

    private function defaultMailAndEnvelope(): array
    {
        $mail = new Email();
        $mail
            ->from('from@mail.com')
            ->to('to@mail.com')
            ->subject('Subject')
            ->text('Some text')
        ;

        $envelope = new DelayedEnvelope($mail);
        $envelope->setRecipients([new Address('recipient@mail.com')]);

        return [$mail, $envelope];
    }
}
