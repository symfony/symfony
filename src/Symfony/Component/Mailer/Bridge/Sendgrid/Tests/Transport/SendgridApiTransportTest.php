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
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SendgridApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SendgridApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
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

        $response = $this->createMock(ResponseInterface::class);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(202);
        $response
            ->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['x-message-id' => '1']);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
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
            ->addPart(new DataPart('Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod', 'lorem.txt'));

        $response = $this->createMock(ResponseInterface::class);

        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(202);
        $response
            ->expects($this->once())
            ->method('getHeaders')
            ->willReturn(['x-message-id' => '1']);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects($this->once())
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
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('headers', $payload);
        $this->assertArrayHasKey('foo', $payload['headers']);
        $this->assertEquals('bar', $payload['headers']['foo']);
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
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('from', $payload);
        $this->assertArrayHasKey('email', $payload['from']);
        $this->assertSame($from, $payload['from']['email']);

        $this->assertArrayHasKey('reply_to', $payload);
        $this->assertArrayHasKey('email', $payload['reply_to']);
        $this->assertSame($replyTo, $payload['reply_to']['email']);
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
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('from', $payload);
        $this->assertArrayHasKey('email', $payload['from']);
        $this->assertSame($envelopeFrom, $payload['from']['email']);

        $this->assertArrayHasKey('personalizations', $payload);
        $this->assertArrayHasKey('to', $payload['personalizations'][0]);
        $this->assertArrayHasKey('email', $payload['personalizations'][0]['to'][0]);
        $this->assertCount(1, $payload['personalizations'][0]['to']);
        $this->assertSame($envelopeTo, $payload['personalizations'][0]['to'][0]['email']);
    }

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('category-one'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new SendgridApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendgridApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('categories', $payload);
        $this->assertArrayHasKey('custom_args', $payload['personalizations'][0]);

        $this->assertCount(1, $payload['categories']);
        $this->assertCount(2, $payload['personalizations'][0]['custom_args']);

        $this->assertSame(['category-one'], $payload['categories']);
        $this->assertSame('blue', $payload['personalizations'][0]['custom_args']['Color']);
        $this->assertSame('12345', $payload['personalizations'][0]['custom_args']['Client-ID']);
    }
}
