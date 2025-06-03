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

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\AhaSend\Event\AhaSendDeliveryEvent;
use Symfony\Component\Mailer\Bridge\AhaSend\Transport\AhaSendApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AhaSendApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(AhaSendApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            [
                new AhaSendApiTransport('KEY'),
                'ahasend+api://send.ahasend.com',
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

    public function testSend()
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

    public function testSendDeliveryEventIsDispatched()
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

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
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

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AhaSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
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
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
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
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
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
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
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
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
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
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
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
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
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
        $method = new \ReflectionMethod(AhaSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('attachments', $payload['content']);
        $this->assertCount(1, $payload['content']['attachments']);
        $this->assertArrayHasKey('base64', $payload['content']['attachments'][0]);

        $this->assertFalse($payload['content']['attachments'][0]['base64']);
    }
}
