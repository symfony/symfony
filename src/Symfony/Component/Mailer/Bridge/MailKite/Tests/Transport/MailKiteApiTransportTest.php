<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailKite\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\MailKite\Transport\MailKiteApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailKiteApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(MailKiteApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new MailKiteApiTransport('KEY'),
                'mailkite+api://api.mailkite.dev',
            ],
            [
                (new MailKiteApiTransport('KEY'))->setHost('example.com'),
                'mailkite+api://example.com',
            ],
            [
                (new MailKiteApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'mailkite+api://example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $email = new Email();
        $email->from(new Address('foo@example.com', 'Ms. Foo Bar'))
            ->to(new Address('bar@example.com', 'Mr. Recipient'))
            ->cc('cc@example.com')
            ->bcc('baz@example.com')
            ->replyTo(new Address('reply@example.com', 'Ms. Reply'))
            ->subject('An email')
            ->text('Test email body')
            ->html('<html lang="en"><body><p>Test email body</p></body></html>');
        $email->getHeaders()->addTextHeader('X-Tag', 'welcome');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mailkite.dev/v1/send', $url);
            $this->assertStringContainsString('Authorization: Bearer KEY', implode("\n", $options['headers']));

            $body = json_decode($options['body'], true);
            $this->assertSame('"Ms. Foo Bar" <foo@example.com>', $body['from']);
            $this->assertSame(['"Mr. Recipient" <bar@example.com>'], $body['to']);
            $this->assertSame(['cc@example.com'], $body['cc']);
            $this->assertSame(['baz@example.com'], $body['bcc']);
            $this->assertSame('"Ms. Reply" <reply@example.com>', $body['replyTo']);
            $this->assertSame('An email', $body['subject']);
            $this->assertSame('Test email body', $body['text']);
            $this->assertStringContainsString('Test email body', $body['html']);
            $this->assertSame('welcome', $body['headers']['X-Tag']);

            return new JsonMockResponse(['id' => 'msg_1234', 'status' => 'queued'], ['http_code' => 202]);
        });

        $transport = new MailKiteApiTransport('KEY', $client);
        $sentMessage = $transport->send($email);

        $this->assertSame('msg_1234', $sentMessage->getMessageId());
    }

    public function testSendWithAttachment()
    {
        $email = new Email();
        $email->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body')
            ->addPart(new DataPart('some text', 'notes.txt', 'text/plain'));

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $this->assertCount(1, $body['attachments']);
            $this->assertSame('notes.txt', $body['attachments'][0]['filename']);
            $this->assertSame('text/plain', $body['attachments'][0]['contentType']);
            $this->assertSame(base64_encode('some text'), $body['attachments'][0]['content']);

            return new JsonMockResponse(['id' => 'msg_1234', 'status' => 'queued'], ['http_code' => 202]);
        });

        (new MailKiteApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithInlineAttachmentThrows()
    {
        $email = new Email();
        $email->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->html('<html lang="en"><body><img src="cid:logo"></body></html>')
            ->addPart((new DataPart('image-bytes', 'logo', 'image/png'))->asInline());

        $transport = new MailKiteApiTransport('KEY', new MockHttpClient());

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('MailKite does not support inline (cid-embedded) attachments');

        $transport->send($email);
    }

    public function testSendWithSeveralReplyToAddresses()
    {
        $email = new Email();
        $email->from('foo@example.com')
            ->to('bar@example.com')
            ->replyTo('one@example.com', 'two@example.com')
            ->subject('An email')
            ->text('Test email body');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $this->assertArrayNotHasKey('replyTo', $body);
            $this->assertSame('one@example.com, two@example.com', $body['headers']['Reply-To']);

            return new JsonMockResponse(['id' => 'msg_1234', 'status' => 'queued'], ['http_code' => 202]);
        });

        (new MailKiteApiTransport('KEY', $client))->send($email);
    }

    public function testSendWithCustomEnvelopeSender()
    {
        $email = new Email();
        $email->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body');

        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $this->assertSame('sender@example.com', $body['from']);
            $this->assertSame(['envelope@example.com'], $body['to']);

            return new JsonMockResponse(['id' => 'msg_1234', 'status' => 'queued'], ['http_code' => 202]);
        });

        $envelope = new Envelope(new Address('sender@example.com'), [new Address('envelope@example.com')]);

        (new MailKiteApiTransport('KEY', $client))->send($email, $envelope);
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(new JsonMockResponse(['error' => 'domain not verified'], ['http_code' => 403]));

        $transport = new MailKiteApiTransport('KEY', $client);
        $email = (new Email())
            ->from('foo@example.com')
            ->to('bar@example.com')
            ->subject('An email')
            ->text('Test email body');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: domain not verified (code 403).');

        $transport->send($email);
    }
}
