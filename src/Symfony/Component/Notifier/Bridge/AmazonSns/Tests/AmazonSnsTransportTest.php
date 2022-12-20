<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AmazonSns\Tests;

use AsyncAws\Sns\Result\PublishResponse;
use AsyncAws\Sns\SnsClient;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsOptions;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AmazonSnsTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null): TransportInterface
    {
        return (new AmazonSnsTransport(new SnsClient(['region' => 'eu-west-3']), $client ?? self::createMock(HttpClientInterface::class)))->setHost('host.test');
    }

    public function toStringProvider(): iterable
    {
        yield ['sns://host.test?region=eu-west-3', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0601020304', 'Hello!')];
        yield [new ChatMessage('Hello', new AmazonSnsOptions('my-topic'))];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [self::createMock(MessageInterface::class)];
        yield [new ChatMessage('hello', self::createMock(MessageOptionsInterface::class))];
    }

    public function testSmsMessageOptions()
    {
        $response = self::createMock(PublishResponse::class);
        $response
            ->expects(self::once())
            ->method('getMessageId')
            ->willReturn('messageId');

        $snsMock = self::getMockBuilder(SnsClient::class)
            ->setConstructorArgs([[]])
            ->getMock();

        $snsMock
            ->expects(self::once())
            ->method('publish')
            ->with(self::equalTo(['PhoneNumber' => '0600000000', 'Message' => 'test']))
            ->willReturn($response);

        $transport = new AmazonSnsTransport($snsMock);
        $transport->send(new SmsMessage('0600000000', 'test'));
    }

    public function testChatMessageOptions()
    {
        $response = self::createMock(PublishResponse::class);
        $response
            ->expects(self::once())
            ->method('getMessageId')
            ->willReturn('messageId');

        $snsMock = self::getMockBuilder(SnsClient::class)
            ->setConstructorArgs([[]])
            ->getMock();

        $snsMock
            ->expects(self::once())
            ->method('publish')
            ->with(self::equalTo(['TopicArn' => 'my-topic', 'Subject' => 'subject', 'Message' => 'Hello World !']))
            ->willReturn($response);

        $options = new AmazonSnsOptions('my-topic');
        $options->subject('subject');

        $transport = new AmazonSnsTransport($snsMock);
        $transport->send(new ChatMessage('Hello World !', $options));
    }
}
