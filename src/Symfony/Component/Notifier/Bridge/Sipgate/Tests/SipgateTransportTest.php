<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sipgate\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Sipgate\SipgateTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SipgateTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): SipgateTransport
    {
        return new SipgateTransport('tokenid', 'token', 's1', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['sipgate://api.sipgate.com?senderId=s1', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+49123456789', 'Hallo!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hallo!')];
        yield [new DummyMessage()];
    }

    public function testSendSuccessfully()
    {
        $response = new MockResponse('', ['http_code' => 204]);

        $client = new MockHttpClient($response);
        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new SmsMessage('+49123456789', 'Hallo!'));
        $this->assertInstanceOf(SentMessage::class, $sentMessage);
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

        $transport->send(new SmsMessage('+49123456789', 'Hallo!'));
    }

    public static function errorProvider(): iterable
    {
        yield [
            401,
            '',
            'Unable to send SMS with Sipgate: Error code 401 - tokenId or token is wrong.',
        ];
        yield [
            402,
            '',
            'Unable to send SMS with Sipgate: Error code 402 - insufficient funds.',
        ];
        yield [
            403,
            '',
            'Unable to send SMS with Sipgate: Error code 403 - no permisssion to use sms feature or password must be reset or senderId is wrong.',
        ];
        yield [
            415,
            '',
            'Unable to send SMS with Sipgate: Error code 415.',
        ];
    }
}
