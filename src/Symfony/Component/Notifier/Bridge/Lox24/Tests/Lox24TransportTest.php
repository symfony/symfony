<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Lox24\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24Options;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24Transport;
use Symfony\Component\Notifier\Bridge\Lox24\Type;
use Symfony\Component\Notifier\Bridge\Lox24\VoiceLanguage;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
class Lox24TransportTest extends TransportTestCase
{
    private const REQUEST_HEADERS = [
        'X-LOX24-AUTH-TOKEN' => 'user:token',
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'User-Agent' => 'LOX24 Symfony Notifier',
    ];

    private const REQUEST_BODY = [
        'sender_id' => 'testFrom2',
        'phone' => '+1411111111',
        'text' => 'test text',
        'is_text_deleted' => false,
        'delivery_at' => 0,
        'service_code' => 'direct',
    ];

    public static function createTransport(?HttpClientInterface $client = null): Lox24Transport
    {
        return (new Lox24Transport('user', 'token', 'sender', ['type' => 'voice'], $client ?? new MockHttpClient()))->setHost('host.test');
    }

    public static function toStringProvider(): iterable
    {
        yield ['lox24://host.test?from=sender&type=voice', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('+33611223344', 'Hello World!')];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello World!')];
        yield [new PushMessage('subject', 'content')];
    }

    public function testSupportWithNotSmsMessage()
    {
        $transport = new Lox24Transport('user', 'token', 'testFrom');
        $message = $this->createMock(MessageInterface::class);
        $this->assertFalse($transport->supports($message));
    }

    public function testSupportWithNotLOX24Options()
    {
        $transport = new Lox24Transport('user', 'token', 'testFrom');
        $message = new SmsMessage('test', 'test');
        $options = $this->createMock(MessageOptionsInterface::class);
        $message->options($options);
        $this->assertFalse($transport->supports($message));
    }

    public function testSendWithInvalidMessageType()
    {
        $this->expectException(UnsupportedMessageTypeException::class);
        $transport = new Lox24Transport('user', 'token', 'testFrom');
        $message = $this->createMock(MessageInterface::class);
        $transport->send($message);
    }

    public function testMessageFromNotEmpty()
    {
        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom2',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);

        $transport = new Lox24Transport('user', 'token', 'testFrom', [], $client);
        $message = new SmsMessage('+1411111111', 'test text', 'testFrom2');
        $transport->send($message);
    }

    public function testMessageFromEmpty()
    {
        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('user', 'token', 'testFrom', [], $client);
        $message = new SmsMessage('+1411111111', 'test text');
        $transport->send($message);
    }

    public function testMessageFromInvalid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'From" number "???????" is not a valid phone number, shortcode, or alphanumeric sender ID.'
        );
        $transport = new Lox24Transport('user', 'token', '???????', []);
        $message = new SmsMessage('+1411111111', 'test text');
        $transport->send($message);
    }

    public function testOptionIsTextDeleted()
    {
        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => true,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('user', 'token', 'testFrom', [], $client);

        $options = (new Lox24Options())->deleteTextAfterSending(true);
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionDeliveryAtGreaterThanZero()
    {
        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 1000000000,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('user', 'token', 'testFrom', [], $client);

        $options = (new Lox24Options())->deliveryAt((new \DateTimeImmutable())->setTimestamp(1000000000));
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionVoiceLanguageSpanish()
    {
        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'text2speech',
            'voice_lang' => 'ES',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('user', 'token', 'testFrom', [], $client);

        $options = (new Lox24Options())
            ->voiceLanguage(VoiceLanguage::Spanish)
            ->type(Type::Voice);
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionVoiceLanguageAuto()
    {
        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'text2speech',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('user', 'token', 'testFrom', [], $client);

        $options = (new Lox24Options())
            ->voiceLanguage(VoiceLanguage::Auto)
            ->type(Type::Voice);
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionType()
    {
        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);

        $transport = new Lox24Transport('user', 'token', 'testFrom', ['type' => 'voice'], $client);

        $options = (new Lox24Options())->type(Type::Sms);
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionCallbackData()
    {
        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
            'callback_data' => 'callback_data',
        ], [], 201, ['uuid' => '123456']);

        $transport = new Lox24Transport('user', 'token', 'testFrom', ['type' => 'voice'], $client);

        $options = (new Lox24Options())->callbackData('callback_data');
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testResponseStatusCodeNotEqual201()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage(
            'Unable to send the SMS: "service_code: Service\'s code is invalid or unavailable.".'
        );

        $client = $this->mockHttpClient([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ],
            [],
            400,
            [
                'type' => 'https://tools.ietf.org/html/rfc2616#section-10',
                'title' => 'An error occurred',
                'detail' => 'service_code: Service\'s code is invalid or unavailable.',
                'violations' => [
                    [
                        'propertyPath' => 'service_code',
                        'message' => 'Service\'s code is invalid or unavailable.',
                        'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                    ],
                ],
            ],
        );

        $transport = new Lox24Transport('user', 'token', 'testFrom', [], $client);

        $message = new SmsMessage('+1411111111', 'test text');
        $transport->send($message);
    }

    public function mockHttpClient(
        array $bodyOverride = [],
        array $headersOverride = [],
        int $responseStatus = 200,
        array $responseContent = [],
    ): MockHttpClient {
        $body = json_encode(array_merge(self::REQUEST_BODY, $bodyOverride));
        $headers = array_merge(self::REQUEST_HEADERS, $headersOverride);

        $factory = function ($method, $url, $options) use (
            $body,
            $headers,
            $responseStatus,
            $responseContent
        ): MockResponse {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.lox24.eu/sms', $url);
            $this->assertHeaders($headers, $options['headers']);
            $this->assertJsonStringEqualsJsonString($body, $options['body']);

            return new JsonMockResponse($responseContent, [
                'http_code' => $responseStatus,
                'headers' => ['content-type' => 'application/json'],
            ]);
        };

        return new MockHttpClient($factory);
    }

    private function assertHeaders(array $expected, array $headers): void
    {
        foreach ($this->normalizeHeaders($expected) as $expectedHeader) {
            $headerExists = \in_array($expectedHeader, $headers, true);
            $this->assertTrue($headerExists, "Header '$expectedHeader' not found in request's headers");
        }
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[] = $key.': '.$value;
        }

        return $normalized;
    }
}
