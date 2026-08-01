<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\WhatsApp\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\WhatsApp\WhatsAppOptions;
use Symfony\Component\Notifier\Bridge\WhatsApp\WhatsAppTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Piero Recchia <piero.recchia@gmail.com>
 */
final class WhatsAppTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): WhatsAppTransport
    {
        return new WhatsAppTransport('access-token', '123456789', 'v26.0', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['whatsapp://graph.facebook.com?phone_number_id=123456789&api_version=v26.0', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!', (new WhatsAppOptions())->recipientPhoneNumber('5491112345678'))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new DummyMessage()];
    }

    public function testSupportsRequiresARecipient()
    {
        $transport = self::createTransport();

        self::assertFalse($transport->supports(new ChatMessage('Hello!')));
        self::assertFalse($transport->supports(new ChatMessage('Hello!', new WhatsAppOptions())));
    }

    public function testSendFreeFormTextMessageWhenNoTemplateIsSet()
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://graph.facebook.com/v26.0/123456789/messages', $url);
            self::assertContains('Authorization: Bearer access-token', $options['headers']);

            $payload = json_decode($options['body'], true);
            self::assertSame('whatsapp', $payload['messaging_product']);
            self::assertSame('individual', $payload['recipient_type']);
            self::assertSame('5491112345678', $payload['to']);
            self::assertSame('text', $payload['type']);
            self::assertSame('Hello!', $payload['text']['body']);

            return new MockResponse(json_encode(['messages' => [['id' => 'wamid.42']]]));
        });

        $message = new ChatMessage('Hello!', (new WhatsAppOptions())->recipientPhoneNumber('5491112345678'));

        $sentMessage = self::createTransport($client)->send($message);

        self::assertInstanceOf(SentMessage::class, $sentMessage);
        self::assertSame('wamid.42', $sentMessage->getMessageId());
    }

    public function testSendTemplateMessageWithBodyParameters()
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertContains('Authorization: Bearer access-token', $options['headers']);

            $payload = json_decode($options['body'], true);
            self::assertSame('whatsapp', $payload['messaging_product']);
            self::assertSame('individual', $payload['recipient_type']);
            self::assertSame('template', $payload['type']);
            self::assertSame('recordatorio_turno', $payload['template']['name']);
            self::assertSame('es_AR', $payload['template']['language']['code']);
            self::assertSame('Ana', $payload['template']['components'][0]['parameters'][0]['text']);

            return new MockResponse(json_encode(['messages' => [['id' => 'wamid.43']]]));
        });

        $options = (new WhatsAppOptions())
            ->recipientPhoneNumber('5491112345678')
            ->template('recordatorio_turno', 'es_AR', ['Ana', 'Corte de pelo']);

        $sentMessage = self::createTransport($client)->send(new ChatMessage('fallback', $options));

        self::assertSame('wamid.43', $sentMessage->getMessageId());
    }

    public function testSendThrowsWithoutARecipientPhoneNumber()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('recipient phone number');

        self::createTransport()->send(new ChatMessage('Hello!'));
    }

    public function testSendThrowsOnNonSuccessfulResponse()
    {
        $client = new MockHttpClient(new MockResponse(
            json_encode(['error' => ['message' => 'Invalid parameter']]),
            ['http_code' => 400],
        ));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Invalid parameter');

        $message = new ChatMessage('Hello!', (new WhatsAppOptions())->recipientPhoneNumber('5491112345678'));
        self::createTransport($client)->send($message);
    }

    public function testSendThrowsTransportExceptionOnNonJsonErrorResponse()
    {
        $client = new MockHttpClient(new MockResponse('<html><body>502 Bad Gateway</body></html>', ['http_code' => 502]));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('502 Bad Gateway');

        $message = new ChatMessage('Hello!', (new WhatsAppOptions())->recipientPhoneNumber('5491112345678'));
        self::createTransport($client)->send($message);
    }

    public function testSendThrowsTransportExceptionOnNonJsonSuccessfulResponse()
    {
        $client = new MockHttpClient(new MockResponse(''));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the WhatsApp message: malformed response from the WhatsApp Cloud API.');

        $message = new ChatMessage('Hello!', (new WhatsAppOptions())->recipientPhoneNumber('5491112345678'));
        self::createTransport($client)->send($message);
    }
}
