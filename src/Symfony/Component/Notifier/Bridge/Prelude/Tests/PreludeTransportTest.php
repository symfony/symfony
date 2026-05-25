<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Prelude\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Notifier\Bridge\Prelude\PreludeOptions;
use Symfony\Component\Notifier\Bridge\Prelude\PreludeTransport;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Component\Notifier\Tests\Transport\DummyMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class PreludeTransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): PreludeTransport
    {
        return (new PreludeTransport('api-key', '0611223344', $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['prelude://host.test?sender=0611223344', self::createTransport()];
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
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn(json_encode(['code' => 400, 'message' => 'bad request']));

        $client = new MockHttpClient(static fn (): ResponseInterface => $response);

        $transport = self::createTransport($client);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unable to send the SMS: bad request');

        $transport->send(new SmsMessage('phone', 'testMessage', '', (new PreludeOptions())->templateId('tpl_123')));
    }

    public function testSendWithoutTemplateIdThrowsLogicException()
    {
        $transport = self::createTransport();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "template_id" option is required');

        $transport->send(new SmsMessage('phone', 'testMessage'));
    }

    public function testSendWithOptions()
    {
        $expectedBody = [
            'to' => '+33612345678',
            'from' => '0611223344',
            'template_id' => 'tpl_123',
            'variables' => ['order_id' => '12345'],
            'locale' => 'fr-FR',
        ];

        $client = new MockHttpClient(function (string $method, string $url, array $options = []) use ($expectedBody): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://host.test/v2/notify', $url);
            $this->assertContains('Authorization: Bearer api-key', $options['headers']);
            $this->assertEqualsCanonicalizing($expectedBody, json_decode($options['body'], true));

            return new JsonMockResponse(['id' => 'msg_abc123'], ['http_code' => 200]);
        });

        $sentMessage = self::createTransport($client)->send(new SmsMessage(
            '+33612345678',
            'testMessage',
            '',
            (new PreludeOptions())
                ->templateId('tpl_123')
                ->variables(['order_id' => '12345'])
                ->locale('fr-FR'),
        ));

        $this->assertSame('msg_abc123', $sentMessage->getMessageId());
        $this->assertSame('prelude://host.test?sender=0611223344', $sentMessage->getTransport());
    }
}
