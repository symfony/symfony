<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Smsense\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Smsense\SmsenseTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SmsenseTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): SmsenseTransport
    {
        return new SmsenseTransport('api_token', 'Symfony', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['smsense://rest.smsense.com?from=Symfony', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+40701111111', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendSuccessfully()
    {
        $response = new JsonMockResponse([
            'status' => 'created',
            'direction' => 'outgoing',
            'from' => '+40702222222',
            'created' => '2024-02-02T20:35:32.429389',
            'parts' => 1,
            'to' => '+40701111111',
            'cost' => 3900,
            'message' => 'Symfony test',
            'message_id' => '63444830-5857-50da-d5f6-69f3719aa916',
        ]);

        $client = new MockHttpClient($response);
        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new SmsMessage('+40701111111', 'Hello!'));
        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertSame('63444830-5857-50da-d5f6-69f3719aa916', $sentMessage->getMessageId());
    }

    /**
     * @dataProvider errorProvider
     */
    public function testExceptionIsThrownWhenSendFailed(int $statusCode, string $content, string $expectedExceptionMessage)
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getContent')->willReturn($content);
        $client = new MockHttpClient($response);
        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $transport->send(new SmsMessage('+40701111111', 'Hello!'));
    }

    public static function errorProvider(): iterable
    {
        yield [
            401,
            'API access requires Basic HTTP authentication. Read documentation or examples.',
            'Unable to post the SMSense message: API access requires Basic HTTP authentication. Read documentation or examples.',
        ];
        yield [
            403,
            'Missing key from',
            'Unable to post the SMSense message: Missing key from',
        ];
    }
}
