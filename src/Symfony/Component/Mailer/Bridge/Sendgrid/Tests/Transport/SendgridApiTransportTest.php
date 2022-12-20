<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendgrid\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SendgridApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SendgridApiTransport $transport, string $expected)
    {
        self::assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new SendgridApiTransport('KEY'),
                'sendgrid+api://api.sendgrid.com',
            ],
            [
                (new SendgridApiTransport('KEY'))->setHost('example.com'),
                'sendgrid+api://example.com',
            ],
            [
                (new SendgridApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'sendgrid+api://example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->bcc('baz@example.com')
            ->text('content');

        $response = self::createMock(ResponseInterface::class);

        $response
            ->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(202);
        $response
            ->expects(self::once())
            ->method('getHeaders')
            ->willReturn(['x-message-id' => '1']);

        $httpClient = self::createMock(HttpClientInterface::class);

        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'json' => [
                    'personalizations' => [
                        [
                            'to' => [[
                                'email' => 'bar@example.com',
                                'name' => 'Mr. Recipient',
                            ]],
                            'subject' => null,
                            'bcc' => [['email' => 'baz@example.com']],
                        ],
                    ],
                    'from' => [
                        'email' => 'foo@example.com',
                        'name' => 'Ms. Foo Bar',
                    ],
                    'content' => [
                        ['type' => 'text/plain', 'value' => 'content'],
                    ],
                ],
                'auth_bearer' => 'foo',
            ])
            ->willReturn($response);

        $mailer = new SendgridApiTransport('foo', $httpClient);
        $mailer->send($email);
    }

    public function testLineBreaksInEncodedAttachment()
    {
        $email = new Email();
        $email->from('foo@example.com')
            ->to('bar@example.com')
            // even if content doesn't include new lines, the base64 encoding performed later may add them
            ->attach('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod', 'lorem.txt');

        $response = self::createMock(ResponseInterface::class);

        $response
            ->expects(self::once())
            ->method('getStatusCode')
            ->willReturn(202);
        $response
            ->expects(self::once())
            ->method('getHeaders')
            ->willReturn(['x-message-id' => '1']);

        $httpClient = self::createMock(HttpClientInterface::class);

        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'json' => [
                    'personalizations' => [
                        [
                            'to' => [['email' => 'bar@example.com']],
                            'subject' => null,
                        ],
                    ],
                    'from' => ['email' => 'foo@example.com'],
                    'content' => [],
                    'attachments' => [
                        [
                            'content' => 'TG9yZW0gaXBzdW0gZG9sb3Igc2l0IGFtZXQsIGNvbnNlY3RldHVyIGFkaXBpc2NpbmcgZWxpdCwgc2VkIGRvIGVpdXNtb2Q=',
                            'filename' => 'lorem.txt',
                            'type' => 'application/octet-stream',
                            'disposition' => 'attachment',
                        ],
                    ],
                ],
                'auth_bearer' => 'foo',
            ])
            ->willReturn($response);

        $mailer = new SendgridApiTransport('foo', $httpClient);

        $mailer->send($email);
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new SendgridApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        self::assertArrayHasKey('headers', $payload);
        self::assertArrayHasKey('foo', $payload['headers']);
        self::assertEquals('bar', $payload['headers']['foo']);
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

        $transport = new SendgridApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        self::assertArrayHasKey('from', $payload);
        self::assertArrayHasKey('email', $payload['from']);
        self::assertSame($from, $payload['from']['email']);

        self::assertArrayHasKey('reply_to', $payload);
        self::assertArrayHasKey('email', $payload['reply_to']);
        self::assertSame($replyTo, $payload['reply_to']['email']);
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
        $envelope = new Envelope(new Address($envelopeFrom), [new Address($envelopeTo)]);

        $transport = new SendgridApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        self::assertArrayHasKey('from', $payload);
        self::assertArrayHasKey('email', $payload['from']);
        self::assertSame($envelopeFrom, $payload['from']['email']);

        self::assertArrayHasKey('personalizations', $payload);
        self::assertArrayHasKey('to', $payload['personalizations'][0]);
        self::assertArrayHasKey('email', $payload['personalizations'][0]['to'][0]);
        self::assertCount(1, $payload['personalizations'][0]['to']);
        self::assertSame($envelopeTo, $payload['personalizations'][0]['to'][0]['email']);
    }

    public function testTagAndMetadataHeaders()
    {
        if (!class_exists(TagHeader::class)) {
            self::markTestSkipped('This test requires symfony/mailer 5.1 or higher.');
        }

        $email = new Email();
        $email->getHeaders()->add(new TagHeader('category-one'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new SendgridApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        self::assertArrayHasKey('categories', $payload);
        self::assertArrayHasKey('custom_args', $payload['personalizations'][0]);

        self::assertCount(1, $payload['categories']);
        self::assertCount(2, $payload['personalizations'][0]['custom_args']);

        self::assertSame(['category-one'], $payload['categories']);
        self::assertSame('blue', $payload['personalizations'][0]['custom_args']['Color']);
        self::assertSame('12345', $payload['personalizations'][0]['custom_args']['Client-ID']);
    }
}
