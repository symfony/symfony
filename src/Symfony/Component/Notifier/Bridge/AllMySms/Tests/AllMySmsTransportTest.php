<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AllMySms\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsOptions;
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AllMySmsTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null, ?string $from = null): AllMySmsTransport
    {
        return new AllMySmsTransport('login', 'apiKey', $from, $client ?? new MockHttpClient());
    }

    public static function toStringProvider(): iterable
    {
        yield ['allmysms://api.allmysms.com', self::createTransport()];
        yield ['allmysms://api.allmysms.com?from=TEST', self::createTransport(null, 'TEST')];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new SmsMessage('0611223344', 'Hello!', 'from', new AllMySmsOptions(['from' => 'foo']))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    public function testSentMessageInfo()
    {
        $smsMessage = new SmsMessage('0611223344', 'lorem ipsum');

        $data = json_encode([
            'code' => 100,
            'description' => 'Your messages have been sent',
            'smsId' => 'de4d766d-4faf-11e9-a8ef-0025907cf72e',
            'invalidNumbers' => '',
            'nbContacts' => 1,
            'nbSms' => 1,
            'balance' => 2.81,
            'cost' => 0.19,
        ]);

        $responses = [
            new MockResponse($data, ['http_code' => 201]),
        ];

        $transport = self::createTransport(new MockHttpClient($responses));
        $sentMessage = $transport->send($smsMessage);

        $this->assertSame(1, $sentMessage->getInfo('nbSms'));
        $this->assertSame(2.81, $sentMessage->getInfo('balance'));
        $this->assertSame(0.19, $sentMessage->getInfo('cost'));
    }
}
