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
}
