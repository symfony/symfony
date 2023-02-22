<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FortySixElks\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\FortySixElks\FortySixElksTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FortySixElksTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): FortySixElksTransport
    {
        return new FortySixElksTransport('api_username', 'api_password', 'Symfony', $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['forty-six-elks://api.46elks.com?from=Symfony', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+46701111111', 'Hello!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSendSuccessfully()
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(file_get_contents(__DIR__.'/Fixtures/success-response.json'));
        $client = new MockHttpClient($response);
        $transport = $this->createTransport($client);
        $sentMessage = $transport->send(new SmsMessage('+46701111111', 'Hello!'));

        $this->assertInstanceOf(SentMessage::class, $sentMessage);
        $this->assertSame('s0231d6d7d6bc14a7e7734e466785c4ce', $sentMessage->getMessageId());
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

        $transport->send(new SmsMessage('+46701111111', 'Hello!'));
    }

    public static function errorProvider(): iterable
    {
        yield [
            401,
            'API access requires Basic HTTP authentication. Read documentation or examples.',
            'Unable to post the 46elks message: API access requires Basic HTTP authentication. Read documentation or examples.',
        ];
        yield [
            403,
            'Missing key from',
            'Unable to post the 46elks message: Missing key from',
        ];
    }
}
