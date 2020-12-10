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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsOptions;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\SmsMessage;

class AmazonTransportTest extends TestCase
{
    public function testSupportsMessageInterface()
    {
        $transport = new AmazonSnsTransport($this->createMock(SnsClient::class));

        $this->assertTrue($transport->supports(new SmsMessage('0611223344', 'Hello!')));
        $this->assertTrue($transport->supports(new ChatMessage('arn:topic')));
        $this->assertFalse($transport->supports($this->createMock(MessageInterface::class)));
    }

    public function testSendMessageWithUnsupportedMessageThrows()
    {
        $transport = new AmazonSnsTransport($this->createMock(SnsClient::class));

        $this->expectException(LogicException::class);
        $transport->send($this->createMock(MessageInterface::class));
    }

    public function testChatMessageWithInvalidOptionsThrows()
    {
        $transport = new AmazonSnsTransport($this->createMock(SnsClient::class));

        $this->expectException(LogicException::class);
        $transport->send(new ChatMessage('topic', $this->createMock(MessageOptionsInterface::class)));
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
            ->with($this->equalTo(['TopicArn' => 'my-topic', 'random' => 'value', 'Message' => 'Subject']))
            ->willReturn($response);

        $transport = new AmazonSnsTransport($snsMock);
        $transport->send(new ChatMessage('Subject', new AmazonSnsOptions('my-topic', ['random' => 'value'])));
    }
}
