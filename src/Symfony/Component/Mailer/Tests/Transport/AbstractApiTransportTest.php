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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AbstractApiTransportTest extends TestCase
{
    public function testSendThrowsWhenTheOriginalMessageIsNotAMimeMessage()
    {
        $transport = new class(new MockHttpClient()) extends AbstractApiTransport {
            protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
            {
                throw new \BadMethodCallException('This method should never be called.');
            }

            public function __toString(): string
            {
                return 'api://test';
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Unable to send message with the "%s" transport: the message must be an instance of "%s" (got "%s"). An API transport builds its payload from the message fields; use an SMTP transport to send a raw message.', $transport::class, Message::class, RawMessage::class));

        $transport->send(new RawMessage('Raw email content'), new Envelope(new Address('fabien@symfony.com'), [new Address('helene@symfony.com')]));
    }

    public function testSendPreservesTheGeneratedMessageIdOnTheApiEmail()
    {
        $transport = new class(new MockHttpClient()) extends AbstractApiTransport {
            public ?Email $capturedEmail = null;

            protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
            {
                $this->capturedEmail = $email;

                return new MockResponse();
            }

            public function __toString(): string
            {
                return 'api://test';
            }
        };

        $email = (new Email())
            ->from('fabien@symfony.com')
            ->to('helene@symfony.com')
            ->text('Hello there!');

        $sentMessage = $transport->send($email);

        // The email handed to the API must carry the Message-ID header generated for
        // this message, the way the SMTP transport sends it via SentMessage::getMessage().
        $this->assertNotSame('', $sentMessage->getMessageId());
        $this->assertInstanceOf(Email::class, $transport->capturedEmail);
        $this->assertSame('<'.$sentMessage->getMessageId().'>', $transport->capturedEmail->getHeaders()->get('Message-ID')->getBodyAsString());

        // The caller's original message must not be mutated in the process.
        $this->assertFalse($email->getHeaders()->has('Message-ID'));
    }
}
