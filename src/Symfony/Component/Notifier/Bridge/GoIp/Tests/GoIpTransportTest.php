<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoIp\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\GoIp\GoIpOptions;
use Symfony\Component\Notifier\Bridge\GoIp\GoIpTransport;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Ahmed Ghanem <ahmedghanem7361@gmail.com>
 */
final class GoIpTransportTest extends TransportTestCase
{
    public static function toStringProvider(): iterable
    {
        yield ['goip://host.test:4000?sim_slot=4', self::createTransport()];
    }

    public static function createTransport(?HttpClientInterface $client = null): GoIpTransport
    {
        return (new GoIpTransport('user', 'pass', 4, $client ?? new MockHttpClient()))
            ->setHost('host.test')
            ->setPort(4000);
    }

    public static function supportedMessagesProvider(): iterable
    {
        $message = new SmsMessage('0611223344', 'Hello!');
        $message->options((new GoIpOptions())->setSimSlot(3));

        yield [$message];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new DummyMessage()];
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testSendMessage()
    {
        $successReply = 'Sending,L5 Send SMS to:0123; ID:'.($messageId = 'dj282jjs8');

        $mockClient = new MockHttpClient(new MockResponse($successReply));
        $sentMessage = self::createTransport($mockClient)->send(new SmsMessage('0123', 'Test'));

        $this->assertSame($messageId, $sentMessage->getMessageId());
    }

    /**
     * @dataProvider goipErrorsProvider
     *
     * @throws TransportExceptionInterface
     */
    public function testSendMessageWithUnsuccessfulReplyFromGoipThrows(string $goipError)
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage(\sprintf('Could not send the message through GoIP. Response: "%s".', $goipError));

        $mockClient = new MockHttpClient(new MockResponse($goipError));

        self::createTransport($mockClient)->send(new SmsMessage('1', 'Test'));
    }

    public function goipErrorsProvider(): iterable
    {
        yield ['ERROR,L10 GSM logout'];
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testSendMessageWithSuccessfulReplyButNoMessageIdThrows()
    {
        $misFormedReply = 'Sending,L5 Send SMS to:0123';

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage(\sprintf('Could not extract the message id from the GoIP response: "%s".', $misFormedReply));

        $mockClient = new MockHttpClient(new MockResponse($misFormedReply));

        self::createTransport($mockClient)->send(new SmsMessage('0123', 'Test'));
    }
}
