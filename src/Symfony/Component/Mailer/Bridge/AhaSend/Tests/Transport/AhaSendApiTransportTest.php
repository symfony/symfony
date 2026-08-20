<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\AhaSend\Event\AhaSendDeliveryEvent;
use Symfony\Component\Mailer\Bridge\AhaSend\Transport\AhaSendApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AhaSendApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(AhaSendApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            // v1
            [
                new AhaSendApiTransport('KEY'),
                'ahasend+api://send.ahasend.com',
            ],
            // v2
            [
                new AhaSendApiTransport('aha-sk-KEY', accountId: 'ACCOUNT-ID'),
                'ahasend+api://api.ahasend.com',
            ],
            [
                (new AhaSendApiTransport('KEY'))->setHost('example.com'),
                'ahasend+api://example.com',
            ],
            [
                (new AhaSendApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'ahasend+api://example.com:99',
            ],
        ];
    }

    public function testV2ApiKeyWithoutAccountId()
    {
        $this->expectException(IncompleteDsnException::class);
        $this->expectExceptionMessage('A v2 AhaSend API key requires an account id: use the "ahasend+api://API_KEY:ACCOUNT_ID@default" DSN.');

        new AhaSendApiTransport('aha-sk-KEY');
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSendV1()
    {
        $this->expectUserDeprecationMessage('Since symfony/aha-send-mailer 8.2: Sending through the legacy AhaSend v1 API is deprecated, use a v2 API key and add your account id to the DSN.');

        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->bcc('baz@example.com')
            ->subject('An email')
            ->text('Test email body')
            ->html('<html lang="en"><body><p>Test email body</p></body></html>')
            ->replyTo(new Address('bar2@example.com', 'Mr. Recipient'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://send.ahasend.com/v1/email/send', $url);
            $this->assertStringContainsString('X-Api-Key: foo', $options['headers'][0] ?? $options['request_headers'][0]);

            $body = json_decode($options['body'], true);
            $this->assertSame('foo@example.com', $body['from']['email']);
            $this->assertSame('Ms. Foo Bar', $body['from']['name']);
            $this->assertSame('bar@example.com', $body['recipients'][0]['email']);
            $this->assertSame('Mr. Recipient', $body['recipients'][0]['name']);
            $this->assertSame('baz@example.com', $body['recipients'][1]['email']);
            $this->assertArrayNotHasKey('name', $body['recipients'][1]);
            $this->assertSame('An email', $body['content']['subject']);
            $this->assertSame('Test email body', $body['content']['text_body']);
            $this->assertSame('<html lang="en"><body><p>Test email body</p></body></html>', $body['content']['html_body']);
            $this->assertSame('bar2@example.com', $body['content']['reply_to']['email']);
            $this->assertSame('Mr. Recipient', $body['content']['reply_to']['name']);
            $this->assertSame('baz@example.com', $body['content']['headers']['Bcc']);

            return new JsonMockResponse([
                'success_count' => 3,
                'fail_count' => 0,
                'failed_recipients' => [],
                'errors' => [],
            ], [
                'http_code' => 201,
            ]);
        });

        $mailer = new AhaSendApiTransport('foo', $client);
        $mailer->send($email);
    }

    public function testSendV2()
    {
        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->bcc('baz@example.com')
            ->subject('An email')
            ->text('Test email body')
            ->html('<html lang="en"><body><p>Test email body</p></body></html>')
            ->replyTo(new Address('bar2@example.com', 'Mr. Recipient'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.ahasend.com/v2/accounts/SAMPLE-ACCOUNT-ID/messages', $url);
            $this->assertStringContainsString('Authorization: Bearer aha-sk-foo', $options['headers'][0] ?? $options['request_headers'][0]);

            $body = json_decode($options['body'], true);
            $this->assertSame('foo@example.com', $body['from']['email']);
            $this->assertSame('Ms. Foo Bar', $body['from']['name']);
            $this->assertSame('bar@example.com', $body['recipients'][0]['email']);
            $this->assertSame('Mr. Recipient', $body['recipients'][0]['name']);
            $this->assertSame('baz@example.com', $body['recipients'][1]['email']);
            $this->assertArrayNotHasKey('name', $body['recipients'][1]);
            $this->assertSame('An email', $body['subject']);
            $this->assertSame('Test email body', $body['text_content']);
            $this->assertSame('<html lang="en"><body><p>Test email body</p></body></html>', $body['html_content']);
            $this->assertSame('bar2@example.com', $body['reply_to']['email']);
            $this->assertSame('Mr. Recipient', $body['reply_to']['name']);
            $this->assertSame('baz@example.com', $body['headers']['Bcc']);

            return new JsonMockResponse([
                'object' => 'list',
                'data' => [
                    [
                        'object' => 'message',
                        'id' => '3f8e2b1a-7c4d-4e2a-9b1e-2f3a4b5c6d7e',
                        'recipient' => [
                            'email' => 'bar@example.com',
                            'name' => 'Mr. Recipient',
                        ],
                        'status' => 'queued',
                        'error' => null,
                    ],
                    [
                        'object' => 'message',
                        'id' => '8a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
                        'recipient' => [
                            'email' => 'baz@example.com',
                        ],
                        'status' => 'queued',
                        'error' => null,
                    ],
                ],
            ], [
                'http_code' => 202,
            ]);
        });

        $mailer = new AhaSendApiTransport('aha-sk-foo', $client, accountId: 'SAMPLE-ACCOUNT-ID');
        $sentMessage = $mailer->send($email);

        $this->assertSame('3f8e2b1a-7c4d-4e2a-9b1e-2f3a4b5c6d7e', $sentMessage->getMessageId());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testSendDeliveryEventIsDispatchedV1()
    {
        $responseFactory = new JsonMockResponse([
            'success_count' => 0,
            'fail_count' => 1,
            'failed_recipients' => [
                'someone@gmil.com',
            ],
            'errors' => [
                'someone@gmil.com: Invalid recipient',
            ],
        ], [
            'http_code' => 201,
        ]);
        $client = new MockHttpClient($responseFactory);

        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('someone@gmil.com', 'Mr. Someone'))
            ->subject('An email')
            ->text('Test email body');

        $expectedEvent = (new AhaSendDeliveryEvent('someone@gmil.com: Invalid recipient'));

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher
            ->method('dispatch')
            ->willReturnCallback(function ($event) use ($expectedEvent) {
                if ($event instanceof AhaSendDeliveryEvent) {
                    $this->assertEquals($event, $expectedEvent);
                }

                return $event;
            });

        $transport = new AhaSendApiTransport('foo', $client, $dispatcher);

        $transport->send($email);
    }

    public function testSendDeliveryEventIsDispatchedV2PerFailedRecipient()
    {
        $responseFactory = new JsonMockResponse([
            'object' => 'list',
            'data' => [
                [
                    'object' => 'message',
                    'id' => null,
                    'recipient' => [
                        'email' => 'someone@gmil.com',
                        'name' => 'Mr. Someone',
                    ],
                    'status' => 'error',
                    'error' => 'someone@gmil.com: Invalid recipient',
                ],
                [
                    'object' => 'message',
                    'id' => '3f8e2b1a-7c4d-4e2a-9b1e-2f3a4b5c6d7e',
                    'recipient' => [
                        'email' => 'someoneelse@example.com',
                    ],
                    'status' => 'queued',
                    'error' => null,
                ],
                [
                    'object' => 'message',
                    'id' => null,
                    'recipient' => [
                        'email' => 'other@gmil.com',
                    ],
                    'status' => 'error',
                    'error' => 'other@gmil.com: Invalid recipient',
                ],
            ],
        ], [
            'http_code' => 202,
        ]);
        $client = new MockHttpClient($responseFactory);

        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('someone@gmil.com', 'Mr. Someone'), new Address('someoneelse@example.com'), new Address('other@gmil.com'))
            ->subject('An email')
            ->text('Test email body');

        $dispatchedErrors = [];

        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedErrors) {
                if ($event instanceof AhaSendDeliveryEvent) {
                    $dispatchedErrors[] = $event->getMessage();
                }

                return $event;
            });

        $transport = new AhaSendApiTransport('aha-sk-foo', $client, $dispatcher, accountId: 'SAMPLE-ACCOUNT-ID');

        $sentMessage = $transport->send($email);

        $this->assertSame(['someone@gmil.com: Invalid recipient', 'other@gmil.com: Invalid recipient'], $dispatchedErrors);
        $this->assertSame('3f8e2b1a-7c4d-4e2a-9b1e-2f3a4b5c6d7e', $sentMessage->getMessageId());
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('headers', $payload['content']);
        $this->assertArrayHasKey('foo', $payload['content']['headers']);
        $this->assertEquals('bar', $payload['content']['headers']['foo']);
    }

    public function testReplyTo()
    {
        $from = 'from@example.com';
        $to = 'to@example.com';
        $replyTo = 'replyto@example.com';
        $email = new Email();
        $email->from($from)
            ->to($to)
            ->replyTo($replyTo)
            ->text('content');
        $envelope = new Envelope(new Address($from), [new Address($to)]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('from', $payload);
        $this->assertArrayHasKey('email', $payload['from']);
        $this->assertSame($from, $payload['from']['email']);

        $this->assertArrayHasKey('reply_to', $payload['content']);
        $this->assertArrayHasKey('email', $payload['content']['reply_to']);
        $this->assertSame($replyTo, $payload['content']['reply_to']['email']);
    }

    public function testEnvelopeSenderAndRecipients()
    {
        $from = 'from@example.com';
        $to = 'to@example.com';
        $envelopeFrom = 'envelopefrom@example.com';
        $envelopeTo = 'envelopeto@example.com';
        $email = new Email();
        $email->from($from)
            ->to($to)
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->text('content');
        $envelope = new Envelope(new Address($envelopeFrom), [new Address($envelopeTo), new Address('cc@example.com'), new Address('bcc@example.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('from', $payload);
        $this->assertArrayHasKey('email', $payload['from']);
        $this->assertSame($envelopeFrom, $payload['from']['email']);

        $this->assertArrayHasKey('recipients', $payload);
        $this->assertArrayHasKey('email', $payload['recipients'][0]);
        $this->assertCount(3, $payload['recipients']);
        $this->assertSame($envelopeTo, $payload['recipients'][0]['email']);
    }

    public function testTagHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('category-one'));
        $email->getHeaders()->add(new TagHeader('category-two'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('headers', $payload['content']);
        $this->assertArrayHasKey('AhaSend-Tags', $payload['content']['headers']);

        $this->assertCount(1, $payload['content']['headers']);
        $this->assertCount(2, explode(',', $payload['content']['headers']['AhaSend-Tags']));

        $this->assertSame('category-one,category-two', $payload['content']['headers']['AhaSend-Tags']);
    }

    public function testInlineWithCustomContentId()
    {
        $imagePart = (new DataPart('text-contents', 'text.txt'));
        $imagePart->asInline();
        $imagePart->setContentId('content-identifier@symfony');

        $email = new Email();
        $email->addPart($imagePart);
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('attachments', $payload['content']);
        $this->assertCount(1, $payload['content']['attachments']);
        $this->assertArrayHasKey('content_id', $payload['content']['attachments'][0]);

        $this->assertSame('content-identifier@symfony', $payload['content']['attachments'][0]['content_id']);
    }

    public function testInlineWithoutCustomContentId()
    {
        $imagePart = (new DataPart('text-contents', 'text.txt'));
        $imagePart->asInline();

        $email = new Email();
        $email->addPart($imagePart);
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('attachments', $payload['content']);
        $this->assertCount(1, $payload['content']['attachments']);
        $this->assertArrayHasKey('content_id', $payload['content']['attachments'][0]);

        $this->assertSame('text.txt', $payload['content']['attachments'][0]['content_id']);
    }

    public function testAttachmentWithBase64Encoding()
    {
        $textPart = (new DataPart('image-contents', 'image.png'));

        $email = new Email();
        $email->addPart($textPart);
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('attachments', $payload['content']);
        $this->assertCount(1, $payload['content']['attachments']);
        $this->assertArrayHasKey('base64', $payload['content']['attachments'][0]);

        $this->assertTrue($payload['content']['attachments'][0]['base64']);
        $this->assertNotSame('image-contents', $payload['content']['attachments'][0]['data']);
    }

    public function testAttachmentWithoutBase64Encoding()
    {
        $textPart = (new DataPart('text-contents', 'text.txt', 'text/plain'));

        $email = new Email();
        $email->addPart($textPart);
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('attachments', $payload['content']);
        $this->assertCount(1, $payload['content']['attachments']);
        $this->assertArrayHasKey('base64', $payload['content']['attachments'][0]);

        $this->assertFalse($payload['content']['attachments'][0]['base64']);
    }

    public function testTagHeadersV2()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('category-one'));
        $email->getHeaders()->add(new TagHeader('category-two'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('aha-sk-ACCESS_KEY', accountId: 'ACCOUNT_ID');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertSame(['category-one', 'category-two'], $payload['tags']);
        $this->assertArrayNotHasKey('headers', $payload);
        $this->assertFalse($email->getHeaders()->has('AhaSend-Tags'));
    }

    public function testCustomHeaderV2()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('aha-sk-ACCESS_KEY', accountId: 'ACCOUNT_ID');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('headers', $payload);
        $this->assertSame(['foo' => 'bar'], $payload['headers']);
        $this->assertArrayNotHasKey('tags', $payload);
    }

    public function testAttachmentsV2()
    {
        $inlinePart = (new DataPart('text-contents', 'text.txt'));
        $inlinePart->asInline();
        $inlinePart->setContentId('content-identifier@symfony');

        $email = new Email();
        $email->addPart($inlinePart);
        $email->addPart(new DataPart('image-contents', 'image.png'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('aha-sk-ACCESS_KEY', accountId: 'ACCOUNT_ID');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertCount(2, $payload['attachments']);
        $this->assertSame('content-identifier@symfony', $payload['attachments'][0]['content_id']);
        $this->assertTrue($payload['attachments'][1]['base64']);
        $this->assertSame(base64_encode('image-contents'), $payload['attachments'][1]['data']);
    }

    public function testTrackingHeaderV2()
    {
        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(opens: true, clicks: false));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('aha-sk-ACCESS_KEY', accountId: 'ACCOUNT_ID');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertTrue($payload['tracking']['open']);
        $this->assertFalse($payload['tracking']['click']);
        $this->assertArrayNotHasKey('headers', $payload);
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependentlyV2()
    {
        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(opens: false));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('aha-sk-ACCESS_KEY', accountId: 'ACCOUNT_ID');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertFalse($payload['tracking']['open']);
        $this->assertArrayNotHasKey('click', $payload['tracking']);
    }

    public function testTrackingHeaderV1()
    {
        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(opens: true, clicks: false));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('tracking', $payload);
        $this->assertSame('true', $payload['content']['headers']['AhaSend-Track-Opens']);
        $this->assertSame('false', $payload['content']['headers']['AhaSend-Track-Clicks']);
        $this->assertArrayNotHasKey('X-Track', $payload['content']['headers']);
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependentlyV1()
    {
        $email = new Email();
        $email->getHeaders()->add(new TrackingHeader(clicks: false));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('AhaSend-Track-Opens', $payload['content']['headers']);
        $this->assertSame('false', $payload['content']['headers']['AhaSend-Track-Clicks']);
    }

    public function testExplicitAhaSendTrackingHeaderWinsOverTrackingHeaderV1()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('AhaSend-Track-Opens', 'false');
        $email->getHeaders()->add(new TrackingHeader(opens: true));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getLegacyPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertSame('false', $payload['content']['headers']['AhaSend-Track-Opens']);
    }
}
