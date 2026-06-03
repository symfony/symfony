<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Isendpro\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Isendpro\IsendproTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class IsendproTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): IsendproTransport
    {
        return (new IsendproTransport('accound_key_id', null, false, false, $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['isendpro://host.test?no_stop=0&sandbox=0', self::createTransport()];
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

    public function testSendWithErrorResponseThrowsTransportException()
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: error 500.');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendWithErrorResponseContainingDetailsThrowsTransportException()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['etat' => ['etat' => [['code' => '3', 'message' => 'Your credentials are incorrect']]]]), ['http_code' => 400]));

        $transport = $this->createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: error 400. Details from iSendPro: 3: "Your credentials are incorrect".');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendWithSuccessfulResponseDispatchesMessageEvent()
    {
        $client = new MockHttpClient(new MockResponse(json_encode(['etat' => ['etat' => [['code' => 0]]]])));

        $transport = $this->createTransport($client);

        $sentMessage = $transport->send(new SmsMessage('phone', 'testMessage'));

        $this->assertNotNull($sentMessage->getMessageId());
    }
}
