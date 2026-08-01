<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\TurboSmtp\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\TurboSmtp\Transport\TurboSmtpApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TurboSmtpApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(TurboSmtpApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new TurboSmtpApiTransport('KEY', 'SECRET'),
                'turbosmtp+api://api.turbo-smtp.com',
            ],
            [
                (new TurboSmtpApiTransport('KEY', 'SECRET'))->setHost('api.eu.turbo-smtp.com'),
                'turbosmtp+api://api.eu.turbo-smtp.com',
            ],
            [
                (new TurboSmtpApiTransport('KEY', 'SECRET'))->setHost('example.com')->setPort(99),
                'turbosmtp+api://example.com:99',
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
            $this->assertSame('https://api.turbo-smtp.com/api/v2/mail/send', $url);

            $headers = implode("\n", $options['headers']);
            $this->assertStringContainsString('consumerKey: KEY', $headers);
            $this->assertStringContainsString('consumerSecret: SECRET', $headers);

            $body = json_decode($options['body'], true);
            $this->assertStringContainsString('foo@example.com', $body['from']);
            $this->assertStringContainsString('bar@example.com', $body['to']);
            $this->assertSame('An email', $body['subject']);
            $this->assertSame('Test email body', $body['content']);
            $this->assertStringContainsString('Test email body', $body['html_content']);
            $this->assertSame('baz@example.com', $body['bcc']);
            $this->assertStringContainsString('bar2@example.com', $body['custom_headers']['Reply-To']);

            return new JsonMockResponse(['message' => 'OK', 'mid' => 42], ['http_code' => 200]);
        });

        $transport = new TurboSmtpApiTransport('KEY', 'SECRET', $client);
        $message = $transport->send($email);

        $this->assertSame('42', $message->getMessageId());
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new TurboSmtpApiTransport('KEY', 'SECRET');
        $method = new \ReflectionMethod(TurboSmtpApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('custom_headers', $payload);
        $this->assertArrayHasKey('foo', $payload['custom_headers']);
        $this->assertSame('bar', $payload['custom_headers']['foo']);
    }

    public function testReplyTo()
    {
        $email = new Email();
        $email->from('from@example.com')
            ->to('to@example.com')
            ->replyTo('replyto@example.com')
            ->text('content');
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $transport = new TurboSmtpApiTransport('KEY', 'SECRET');
        $method = new \ReflectionMethod(TurboSmtpApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertStringContainsString('replyto@example.com', $payload['custom_headers']['Reply-To']);
    }

    public function testSendWithAttachments()
    {
        $email = new Email();
        $email->from('from@example.com')
            ->to('to@example.com')
            ->subject('An email')
            ->text('content')
            ->addPart(new DataPart('the pdf bytes', 'report.pdf', 'application/pdf'))
            ->addPart((new DataPart('the gif bytes', 'logo.gif', 'image/gif'))->asInline());

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);

            $this->assertCount(2, $body['attachments']);

            $this->assertSame('report.pdf', $body['attachments'][0]['name']);
            $this->assertSame('application/pdf', $body['attachments'][0]['type']);
            $this->assertSame('the pdf bytes', base64_decode($body['attachments'][0]['content']));

            $this->assertSame('logo.gif', $body['attachments'][1]['name']);
            $this->assertSame('image/gif', $body['attachments'][1]['type']);
            $this->assertSame('the gif bytes', base64_decode($body['attachments'][1]['content']));

            return new JsonMockResponse(['message' => 'OK', 'mid' => 42], ['http_code' => 200]);
        });

        (new TurboSmtpApiTransport('KEY', 'SECRET', $client))->send($email);
    }

    public function testSendWithoutAttachmentsOmitsTheKey()
    {
        $email = new Email();
        $email->from('from@example.com')->to('to@example.com')->subject('An email')->text('content');
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $transport = new TurboSmtpApiTransport('KEY', 'SECRET');
        $method = new \ReflectionMethod(TurboSmtpApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('attachments', $payload);
    }

    public function testInlineImageSetsContentIdAndQualifiesTheCidWithTheSenderDomain()
    {
        $email = new Email();
        $email->from('sender@example.com')
            ->to('to@example.com')
            ->subject('An email')
            ->html('<p><img src="cid:logo.gif"></p>')
            ->addPart((new DataPart('the gif bytes', 'logo.gif', 'image/gif'))->asInline());
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('to@example.com')]);

        $transport = new TurboSmtpApiTransport('KEY', 'SECRET');
        $method = new \ReflectionMethod(TurboSmtpApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        // The inline part carries a bare content_id; TurboSMTP appends the sender domain itself.
        $this->assertSame('logo.gif', $payload['attachments'][0]['content_id']);
        // The cid: reference in the HTML is qualified with the sender domain so it resolves inline.
        $this->assertStringContainsString('src="cid:logo.gif@example.com"', $payload['html_content']);
    }

    public function testSendReportsTheErrorsReturnedByTheApi()
    {
        $email = new Email();
        $email->from('from@example.com')->to('to@example.com')->subject('An email')->text('content');

        $client = new MockHttpClient(new JsonMockResponse(
            ['message' => 'error', 'errors' => ['recipient address is not valid', 'quota exceeded']],
            ['http_code' => 400],
        ));

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: recipient address is not valid, quota exceeded (code 400).');

        (new TurboSmtpApiTransport('KEY', 'SECRET', $client))->send($email);
    }

    public function testTechnicalHeadersAreNotForwarded()
    {
        $email = new Email();
        $email->from('from@example.com')->to('to@example.com')->text('content');
        $email->getHeaders()->addTextHeader('X-Custom', 'kept');
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $transport = new TurboSmtpApiTransport('KEY', 'SECRET');
        $method = new \ReflectionMethod(TurboSmtpApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertSame(['X-Custom' => 'kept'], $payload['custom_headers']);
    }

    public function testEnvelopeSenderAndRecipients()
    {
        $email = new Email();
        $email->from('from@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->text('content');
        $envelope = new Envelope(new Address('envelopefrom@example.com'), [new Address('envelopeto@example.com'), new Address('cc@example.com'), new Address('bcc@example.com')]);

        $transport = new TurboSmtpApiTransport('KEY', 'SECRET');
        $method = new \ReflectionMethod(TurboSmtpApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertStringContainsString('envelopefrom@example.com', $payload['from']);
        $this->assertStringContainsString('envelopeto@example.com', $payload['to']);
        $this->assertStringContainsString('cc@example.com', $payload['cc']);
        $this->assertStringContainsString('bcc@example.com', $payload['bcc']);
    }
}
