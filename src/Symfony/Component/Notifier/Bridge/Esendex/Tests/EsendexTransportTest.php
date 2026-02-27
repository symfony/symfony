<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexOptions;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class EsendexTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): EsendexTransport
    {
        return (new EsendexTransport('email', 'password', 'testAccountReference', 'testFrom', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['esendex://host.test?accountreference=testAccountReference&from=testFrom', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new SmsMessage('0611223344', 'Hello!', 'from', new EsendexOptions(['from' => 'foo']))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: error 500.');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendWithErrorResponseContainingDetailsThrowsTransportException()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['errors' => [['code' => 'accountreference_invalid', 'description' => 'Invalid Account Reference EX0000000']]]), ['http_code' => 500]));

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: error 500. Details from Esendex: accountreference_invalid: "Invalid Account Reference EX0000000".');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendWithSuccessfulResponseDispatchesMessageEvent()
    {
        $messageId = bin2hex(random_bytes(7));
        $client = new MockHttpClient(new MockResponse(json_encode(['batch' => ['messageheaders' => [['id' => $messageId]]]])));

        $transport = self::createTransport($client);

        $sentMessage = $transport->send(new SmsMessage('phone', 'testMessage'));

        $this->assertSame($messageId, $sentMessage->getMessageId());
    }

    public function testSentMessageContainsAnArrayOfMessages()
    {
        $requestOptions = [];
        $client = new MockHttpClient(static function ($method, $url, $options) use (&$requestOptions): ResponseInterface {
            $requestOptions = $options;

            return new MockResponse(json_encode(['batch' => ['messageheaders' => [['id' => bin2hex(random_bytes(7))]]]]));
        });

        $transport = self::createTransport($client);

        $transport->send(new SmsMessage('phone', 'testMessage'));

        $body = json_decode($requestOptions['body'] ?? '');

        $this->assertIsArray($body->messages);
        $this->assertCount(1, $body->messages);
        $this->assertSame('phone', $body->messages[0]->to);
        $this->assertSame('testMessage', $body->messages[0]->body);
    }
}
