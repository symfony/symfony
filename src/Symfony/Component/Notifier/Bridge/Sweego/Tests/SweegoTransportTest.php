<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sweego\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Sweego\SweegoTransport;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SweegoTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, string $from = 'from'): SweegoTransport
    {
        return new SweegoTransport('apiKey', 'REGION', 'CAMPAIGN_TYPE', false, 'CAMPAIGN_ID', true, false, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['sweego://api.sweego.io?region=REGION&campaign_type=CAMPAIGN_TYPE&bat=0&campaign_id=CAMPAIGN_ID&shorten_urls=1&shorten_with_protocol=0', self::createTransport()];
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

    public function testSupportWithNotSmsMessage()
    {
        $transport = new SweegoTransport('apiKey', 'REGION', 'CAMPAIGN_TYPE', false, 'CAMPAIGN_ID', true, false);
        $message = $this->createMock(MessageInterface::class);
        $this->assertFalse($transport->supports($message));
    }

    public function testSupportWithNotSweegoOptions()
    {
        $transport = new SweegoTransport('apiKey', 'REGION', 'CAMPAIGN_TYPE', false, 'CAMPAIGN_ID', true, false);
        $message = new SmsMessage('test', 'test');
        $options = $this->createMock(MessageOptionsInterface::class);
        $message->options($options);
        $this->assertFalse($transport->supports($message));
    }

    public function testSendWithInvalidMessageType()
    {
        $this->expectException(UnsupportedMessageTypeException::class);
        $transport = new SweegoTransport('apiKey', 'REGION', 'CAMPAIGN_TYPE', false, 'CAMPAIGN_ID', true, false);
        $message = $this->createMock(MessageInterface::class);
        $transport->send($message);
    }

    public function testSendSmsMessage()
    {
        $client = new MockHttpClient(function ($method, $url, $options) {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.sweego.io/send', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('sms', $body['channel']);

            return new JsonMockResponse(['swg_uids' => ['123']]);
        });

        $transport = self::createTransport($client);
        $sentMessage = $transport->send(new SmsMessage('0611223344', 'Hello!'));

        $this->assertSame('123', $sentMessage->getMessageId());
    }
}
