<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24Options;
use Symfony\Component\Notifier\Bridge\Lox24\Lox24Transport;
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
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Andrei Lebedev <andrew.lebedev@gmail.com>
 */
class Lox24TransportTest extends TransportTestCase
{
    public static function createTransport(?HttpClientInterface $client = null): Lox24Transport
    {
        return (new Lox24Transport('id:token', 'sender', ['type' => 'voice'], $client ?? new MockHttpClient()
        ))->setHost('host.test');
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

    private const REQUEST_HEADERS = [
        'X-LOX24-AUTH-TOKEN' => 'testToken',
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

    private MockObject|HttpClientInterface $client;

    protected function setUp(): void
    {
        $this->client = $this->createMock(HttpClientInterface::class);
    }


    public function testSupportWithNotSmsMessage(): void
    {
        $transport = new Lox24Transport('testToken', 'testFrom');
        $message = $this->createMock(MessageInterface::class);
        $this->assertFalse($transport->supports($message));
    }

    public function testSupportWithNotLOX24Options(): void
    {
        $transport = new Lox24Transport('testToken', 'testFrom');
        $message = new SmsMessage('test', 'test');
        $options = $this->createMock(MessageOptionsInterface::class);
        $message->options($options);
        $this->assertFalse($transport->supports($message));
    }


    public function testSendWithInvalidMessageType(): void
    {
        $this->expectException(UnsupportedMessageTypeException::class);
        $transport = new Lox24Transport('testToken', 'testFrom');
        $message = $this->createMock(MessageInterface::class);
        $transport->send($message);
    }

    public function testMessageFromNotEmpty(): void
    {
        $this->assertRequestBody([
            'sender_id' => 'testFrom2',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('testToken', 'testFrom', [], $this->client);
        $message = new SmsMessage('+1411111111', 'test text', 'testFrom2');
        $transport->send($message);
    }

    public function testMessageFromEmpty(): void
    {
        $this->assertRequestBody([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('testToken', 'testFrom', [], $this->client);
        $message = new SmsMessage('+1411111111', 'test text');
        $transport->send($message);
    }

    public function testMessageFromInvalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'From" number "???????" is not a valid phone number, shortcode, or alphanumeric sender ID.'
        );
        $transport = new Lox24Transport('testToken', '???????', []);
        $message = new SmsMessage('+1411111111', 'test text');
        $transport->send($message);
    }

    public function testOptionIsTextDeleted(): void
    {
        $this->assertRequestBody([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => true,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('testToken', 'testFrom', [], $this->client);

        $options = (new Lox24Options())->textDelete(true);
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionDeliveryAtGreaterThanZero(): void
    {
        $this->assertRequestBody([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 1000000000,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('testToken', 'testFrom', [], $this->client);

        $options = (new Lox24Options())->deliveryAt((new DateTimeImmutable())->setTimestamp(1000000000));
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionVoiceLanguageSpanish(): void
    {
        $this->assertRequestBody([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'text2speech',
            'voice_lang' => 'es',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('testToken', 'testFrom', [], $this->client);

        $options = (new Lox24Options())->voiceLanguage('es')->type('voice');
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionVoiceLanguageAuto(): void
    {
        $this->assertRequestBody([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'text2speech',
        ], [], 201, ['uuid' => '123456']);
        $transport = new Lox24Transport('testToken', 'testFrom', [], $this->client);

        $options = (new Lox24Options())->voiceLanguage('auto')->type('voice');
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionType(): void
    {
        $this->assertRequestBody([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
        ], [], 201, ['uuid' => '123456']);

        $transport = new Lox24Transport('testToken', 'testFrom', ['type' => 'voice'], $this->client);

        $options = (new Lox24Options())->type('sms');
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testOptionCallbackData(): void
    {
        $this->assertRequestBody([
            'sender_id' => 'testFrom',
            'phone' => '+1411111111',
            'text' => 'test text',
            'is_text_deleted' => false,
            'delivery_at' => 0,
            'service_code' => 'direct',
            'callback_data' => 'callback_data',
        ], [], 201, ['uuid' => '123456']);

        $transport = new Lox24Transport('testToken', 'testFrom', ['type' => 'voice'], $this->client);

        $options = (new Lox24Options())->callbackData('callback_data');
        $message = new SmsMessage('+1411111111', 'test text');
        $message->options($options);

        $transport->send($message);
    }

    public function testResponseStatusCodeNotEqual201(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage(
            'Unable to send the SMS: service_code: Service\'s code is invalid or unavailable.'
        );

        $this->assertRequestBody([
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

        $transport = new Lox24Transport('testToken', 'testFrom', [], $this->client);

        $message = new SmsMessage('+1411111111', 'test text');
        $transport->send($message);
    }

    private function assertRequestBody(
        array $bodyOverride = [],
        array $headersOverride = [],
        int $responseStatus = 200,
        array $responseContent = []
    ): void {
        $body = array_merge(self::REQUEST_BODY, $bodyOverride);
        $headers = array_merge(self::REQUEST_HEADERS, $headersOverride);
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())->method('getStatusCode')->willReturn($responseStatus);
        $response->expects($this->once())->method('toArray')->willReturn($responseContent);
        $this->client->expects($this->once())
            ->method('request')
            ->with('POST', 'https://api.lox24.eu/sms', [
                'body' => $body,
                'headers' => $headers,
            ])->willReturn($response);
    }
}