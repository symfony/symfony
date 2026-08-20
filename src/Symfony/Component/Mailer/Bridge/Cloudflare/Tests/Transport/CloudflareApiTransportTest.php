<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Cloudflare\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Cloudflare\Transport\CloudflareApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author vadage
 */
final class CloudflareApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(CloudflareApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN'),
                'cloudflare+api://api.cloudflare.com',
            ],
            [
                new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN')->setHost('example.com'),
                'cloudflare+api://example.com',
            ],
        ];
    }

    public function testSend()
    {
        $email = new Email()
            ->from(new Address('from@example.com', 'Sender'))
            ->subject('Test subject')
            ->to('to@example.com')
            ->attach('foo', 'example.txt', 'text/plain')
            ->bcc('bcc@example.com')
            ->cc('cc@example.com')
            ->html('<p>Test html</p>')
            ->replyTo('reply@example.com')
            ->text('Test text');

        $email->getHeaders()
            ->add(new MetadataHeader('Custom-Header', 'value'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('POST', $method);
            $this->assertSame('https://api.cloudflare.com/client/v4/accounts/ACCOUNT_ID/email/sending/send', $url);
            $this->assertContains('Authorization: Bearer API_TOKEN', $options['headers']);

            $this->assertSame('from@example.com', $body['from']['address']);
            $this->assertSame('Sender', $body['from']['name']);

            $this->assertSame('Test subject', $body['subject']);

            $this->assertSame('to@example.com', $body['to'][0]['address']);

            $this->assertSame('Zm9v', $body['attachments'][0]['content']);
            $this->assertSame('attachment', $body['attachments'][0]['disposition']);
            $this->assertSame('example.txt', $body['attachments'][0]['filename']);
            $this->assertSame('text/plain', $body['attachments'][0]['type']);

            $this->assertSame('bcc@example.com', $body['bcc'][0]['address']);

            $this->assertSame('cc@example.com', $body['cc'][0]['address']);

            $this->assertArrayHasKey('X-Metadata-Custom-Header', $body['headers']);
            $this->assertsame('value', $body['headers']['X-Metadata-Custom-Header']);

            $this->assertSame('<p>Test html</p>', $body['html']);

            $this->assertSame('reply@example.com', $body['reply_to']['address']);

            $this->assertSame('Test text', $body['text']);

            return new JsonMockResponse([], [
                'http_code' => 200,
            ]);
        });

        $mailer = new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client);
        $mailer->send($email);
    }

    public function testSendUsesTheEnvelopeRecipients()
    {
        $email = new Email()
            ->from('from@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->text('Test text');

        $envelope = new Envelope(new Address('sender@example.com'), [new Address('redirected@example.com')]);

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('sender@example.com', $body['from']['address']);
            $this->assertSame('redirected@example.com', $body['to'][0]['address']);

            return new JsonMockResponse([], ['http_code' => 200]);
        });

        new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client)->send($email, $envelope);
    }

    public function testSendWithMultipleRecipientsAndReplyTo()
    {
        $email = new Email()
            ->from('from@example.com')
            ->to('first@example.com', 'second@example.com')
            ->cc('cc1@example.com', 'cc2@example.com')
            ->bcc('bcc1@example.com', 'bcc2@example.com')
            ->replyTo('reply1@example.com', 'reply2@example.com')
            ->text('Test text');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('first@example.com', $body['to'][0]['address']);
            $this->assertSame('second@example.com', $body['to'][1]['address']);

            $this->assertSame('cc1@example.com', $body['cc'][0]['address']);
            $this->assertSame('cc2@example.com', $body['cc'][1]['address']);

            $this->assertSame('bcc1@example.com', $body['bcc'][0]['address']);
            $this->assertSame('bcc2@example.com', $body['bcc'][1]['address']);

            $this->assertSame('reply1@example.com', $body['reply_to']['address']);

            return new JsonMockResponse([], ['http_code' => 200]);
        });

        new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client)->send($email);
    }

    public function testSendWithUnnamedAddresses()
    {
        $email = new Email()
            ->from('from@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->text('Test text');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('from@example.com', $body['from']['address']);
            $this->assertSame('', $body['from']['name']);

            $this->assertSame('to@example.com', $body['to'][0]['address']);
            $this->assertSame('', $body['to'][0]['name']);

            $this->assertSame('cc@example.com', $body['cc'][0]['address']);
            $this->assertSame('', $body['cc'][0]['name']);

            $this->assertSame('bcc@example.com', $body['bcc'][0]['address']);
            $this->assertSame('', $body['bcc'][0]['name']);

            $this->assertSame('reply@example.com', $body['reply_to']['address']);
            $this->assertSame('', $body['reply_to']['name']);

            return new JsonMockResponse([], ['http_code' => 200]);
        });

        new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client)->send($email);
    }

    public function testSendWithNamedAddresses()
    {
        $email = new Email()
            ->from(new Address('from@example.com', 'From'))
            ->to(new Address('to@example.com', 'To'))
            ->cc(new Address('cc@example.com', 'Cc'))
            ->bcc(new Address('bcc@example.com', 'Bcc'))
            ->replyTo(new Address('reply@example.com', 'Reply'))
            ->text('Test text');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('from@example.com', $body['from']['address']);
            $this->assertSame('From', $body['from']['name']);

            $this->assertSame('to@example.com', $body['to'][0]['address']);
            $this->assertSame('To', $body['to'][0]['name']);

            $this->assertSame('cc@example.com', $body['cc'][0]['address']);
            $this->assertSame('Cc', $body['cc'][0]['name']);

            $this->assertSame('bcc@example.com', $body['bcc'][0]['address']);
            $this->assertSame('Bcc', $body['bcc'][0]['name']);

            $this->assertSame('reply@example.com', $body['reply_to']['address']);
            $this->assertSame('Reply', $body['reply_to']['name']);

            return new JsonMockResponse([], ['http_code' => 200]);
        });

        new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client)->send($email);
    }

    public function testSendWithInlineAttachment()
    {
        $email = new Email()
            ->from('from@example.com')
            ->to('to@example.com')
            ->text('Test text');
        $email->addPart(new DataPart('logo', 'logo.png', 'image/png')->asInline());

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertSame('inline', $body['attachments'][0]['disposition']);
            $this->assertSame('logo.png', $body['attachments'][0]['filename']);
            $this->assertSame('image/png', $body['attachments'][0]['type']);
            $this->assertArrayHasKey('content_id', $body['attachments'][0]);

            return new JsonMockResponse([], ['http_code' => 200]);
        });

        new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client)->send($email);
    }

    public function testSendUsesTheConfiguredHostAndPort()
    {
        $email = new Email()
            ->from('from@example.com')
            ->to('to@example.com')
            ->text('Test text');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('https://example.com:8443/client/v4/accounts/ACCOUNT_ID/email/sending/send', $url);

            return new JsonMockResponse([], ['http_code' => 200]);
        });

        $transport = new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client)
            ->setHost('example.com')
            ->setPort(8443);
        $transport->send($email);
    }

    public function testErrorResponse()
    {
        $email = new Email()
            ->from('from@example.com')
            ->to('to@example.com')
            ->text('Test text');

        $client = new MockHttpClient(static fn (): ResponseInterface => new JsonMockResponse([
            'errors' => [
                [
                    'code' => 10004,
                    'message' => 'email.sending.error.throttled',
                ],
            ],
        ], [
            'http_code' => 429,
        ]));

        $mailer = new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client);

        $this->expectException(HttpTransportException::class);
        $mailer->send($email);
    }

    public function testMalformedResponse()
    {
        $email = new Email()
            ->from('from@example.com')
            ->to('to@example.com')
            ->text('Test text');

        $client = new MockHttpClient(static fn (): ResponseInterface => new MockResponse('Internal server error', [
            'http_code' => 500,
        ]));

        $mailer = new CloudflareApiTransport('ACCOUNT_ID', 'API_TOKEN', $client);

        $this->expectException(HttpTransportException::class);
        $mailer->send($email);
    }
}
