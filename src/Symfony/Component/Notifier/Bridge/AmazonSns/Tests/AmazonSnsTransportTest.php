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
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsOptions;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransport;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Fixtures\TestOptions;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AmazonSnsTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): AmazonSnsTransport
    {
        return (new AmazonSnsTransport(new SnsClient(['region' => 'eu-west-3']), $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['sns://host.test?region=eu-west-3', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0601020304', 'Hello!')];
        yield [new ChatMessage('Hello', new AmazonSnsOptions('my-topic'))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new DummyMessage()];
        yield [new ChatMessage('hello', new TestOptions())];
    }

    public function testSmsMessageWithFrom()
    {
        $transport = $this->createTransport();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransport" transport does not support "from" in "Symfony\Component\Notifier\Message\SmsMessage".');

        $transport->send(new SmsMessage('0600000000', 'test', 'foo'));
    }

    public function testSmsMessageOptions()
    {
        $response = $this->createMock(PublishResponse::class);
        $response
            ->expects($this->once())
            ->method('getMessageId')
            ->willReturn('messageId');

        $snsMock = $this->getMockBuilder(SnsClient::class)
            ->setConstructorArgs([[]])
            ->getMock();

        $snsMock
            ->expects($this->once())
            ->method('publish')
            ->with($this->equalTo(['PhoneNumber' => '0600000000', 'Message' => 'test']))
            ->willReturn($response);

        $transport = new AmazonSnsTransport($snsMock);
        $transport->send(new SmsMessage('0600000000', 'test'));
    }

    public function testChatMessageOptions()
    {
        $response = $this->createMock(PublishResponse::class);
        $response
            ->expects($this->once())
            ->method('getMessageId')
            ->willReturn('messageId');

        $snsMock = $this->getMockBuilder(SnsClient::class)
            ->setConstructorArgs([[]])
            ->getMock();

        $snsMock
            ->expects($this->once())
            ->method('publish')
            ->with($this->equalTo(['TopicArn' => 'my-topic', 'Subject' => 'subject', 'Message' => 'Hello World !']))
            ->willReturn($response);

        $options = new AmazonSnsOptions('my-topic');
        $options->subject('subject');

        $transport = new AmazonSnsTransport($snsMock);
        $transport->send(new ChatMessage('Hello World !', $options));
    }
}
