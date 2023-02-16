<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsBiuras\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\SmsBiuras\SmsBiurasTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SmsBiurasTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): SmsBiurasTransport
    {
        return new SmsBiurasTransport('uid', 'api_key', 'from', true, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['smsbiuras://savitarna.smsbiuras.lt?from=from&test_mode=1', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    /**
     * @dataProvider provideTestMode()
     */
    public function testTestMode(int $expected, bool $testMode)
    {
        $message = new SmsMessage('0037012345678', 'Hello World');

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->atLeast(1))
            ->method('getStatusCode')
            ->willReturn(200);
        $response->expects($this->atLeast(1))
            ->method('getContent')
            ->willReturn('OK: 519545');

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($response, $message, $expected): ResponseInterface {
            $this->assertSame('GET', $method);
            $this->assertSame(sprintf(
                'https://savitarna.smsbiuras.lt/api?uid=uid&apikey=api_key&message=%s&from=from&test=%s&to=%s',
                rawurlencode($message->getSubject()),
                $expected,
                rawurlencode($message->getPhone())
            ), $url);
            $this->assertSame($expected, $options['query']['test']);

            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame('OK: 519545', $response->getContent());

            return $response;
        });

        $transport = new SmsBiurasTransport('uid', 'api_key', 'from', $testMode, $client);

        $sentMessage = $transport->send($message);

        $this->assertSame('519545', $sentMessage->getMessageId());
    }

    public static function provideTestMode(): iterable
    {
        yield [1, true];
        yield [0, false];
    }
}
