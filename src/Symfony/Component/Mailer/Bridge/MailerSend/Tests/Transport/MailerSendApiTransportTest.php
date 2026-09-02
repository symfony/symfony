<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MailerSend\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\MailerSend\Transport\MailerSendApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\Header\TrackingHeader;
use Symfony\Component\Mailer\RemoteTemplateEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailerSendApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(MailerSendApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        yield [
            new MailerSendApiTransport('ACCESS_KEY'),
            'mailersend+api://api.mailersend.com',
        ];

        yield [
            (new MailerSendApiTransport('ACCESS_KEY'))->setHost('example.com'),
            'mailersend+api://example.com',
        ];

        yield [
            (new MailerSendApiTransport('ACCESS_KEY'))->setHost('example.com')->setPort(99),
            'mailersend+api://example.com:99',
        ];
    }

    public function testSendBasicEmail()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mailersend.com/v1/email', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame('test_from@example.com', $body['from']['email']);
            $this->assertSame('Test from name', $body['from']['name']);
            $this->assertSame('test_to@example.com', $body['to'][0]['email']);
            $this->assertSame('Test to name', $body['to'][0]['name']);
            $this->assertSame('Test subject', $body['subject']);
            $this->assertSame('Lorem ipsum.', $body['text']);
            $this->assertSame('<html><body><p>Lorem ipsum.</p></body></html>', $body['html']);

            return new MockResponse('', [
                'http_code' => 202,
                'response_headers' => ['x-message-id' => 'test_message_id'],
            ]);
        });

        $transport = new MailerSendApiTransport('ACCESS_KEY', $client);

        $mail = new Email();
        $mail->subject('Test subject')
            ->to(new Address('test_to@example.com', 'Test to name'))
            ->from(new Address('test_from@example.com', 'Test from name'))
            ->addCc('test_cc@example.com')
            ->addBcc('test_bcc@example.com')
            ->addReplyTo('test_reply_to@example.com')
            ->text('Lorem ipsum.')
            ->html('<html><body><p>Lorem ipsum.</p></body></html>');

        $message = $transport->send($mail);

        $this->assertSame('test_message_id', $message->getMessageId());
    }

    public function testSendEmailWithAttachment()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mailersend.com/v1/email', $url);

            $body = json_decode($options['body'], true);

            $this->assertSame('content', base64_decode($body['attachments'][0]['content']));
            $this->assertSame('attachment.txt', $body['attachments'][0]['filename']);
            $this->assertSame('inline content', base64_decode($body['attachments'][1]['content']));
            $this->assertSame('inline.txt', $body['attachments'][1]['filename']);
            $this->assertSame('inline', $body['attachments'][1]['disposition']);
            $this->assertSame('test_cid@symfony', $body['attachments'][1]['id']);

            return new MockResponse('', [
                'http_code' => 202,
                'response_headers' => ['x-message-id' => 'test_message_id'],
            ]);
        });

        $transport = new MailerSendApiTransport('ACCESS_KEY', $client);

        $mail = new Email();
        $mail->subject('Test subject')
            ->to(new Address('test_to@example.com', 'Test to name'))
            ->from(new Address('test_from@example.com', 'Test from name'))
            ->addCc('test_cc@example.com')
            ->addBcc('test_bcc@example.com')
            ->addReplyTo('test_reply_to@example.com')
            ->html('<html><body><p>Lorem ipsum.</p><img src="cid:test_cid@symfony"></body></html>')
            ->addPart(new DataPart('content', 'attachment.txt', 'text/plain'))
            ->addPart((new DataPart('inline content', 'inline.txt', 'text/plain'))->asInline()->setContentId('test_cid@symfony'));

        $message = $transport->send($mail);

        $this->assertSame('test_message_id', $message->getMessageId());
    }

    public function testRemoteTemplate()
    {
        $email = (new RemoteTemplateEmail())
            ->template('tpl_123', ['firstName' => 'Fabien']);
        $envelope = new Envelope(new Address('alice@system.com', 'Alice'), [new Address('bob@system.com', 'Bob')]);

        $transport = new MailerSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailerSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertSame('tpl_123', $payload['template_id']);
        $this->assertSame([['email' => 'bob@system.com', 'data' => ['firstName' => 'Fabien']]], $payload['personalization']);
        $this->assertArrayNotHasKey('subject', $payload);
        $this->assertArrayNotHasKey('text', $payload);
        $this->assertArrayNotHasKey('html', $payload);
    }

    public function testRemoteTemplateWithSubject()
    {
        $email = (new RemoteTemplateEmail())
            ->subject('Hello!')
            ->template('tpl_123');
        $envelope = new Envelope(new Address('alice@system.com', 'Alice'), [new Address('bob@system.com', 'Bob')]);

        $transport = new MailerSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailerSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertSame('Hello!', $payload['subject']);
        $this->assertSame('tpl_123', $payload['template_id']);
        $this->assertArrayNotHasKey('personalization', $payload);
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(static fn (string $method, string $url, array $options): ResponseInterface => new JsonMockResponse(['message' => 'i\'m a teapot'], [
            'http_code' => 418,
        ]));

        $transport = new MailerSendApiTransport('ACCESS_KEY', $client);

        $mail = new Email();
        $mail->subject('Test subject')
            ->to(new Address('test_to@example.com', 'Test to name'))
            ->from(new Address('test_from@example.com', 'Test from name'))
            ->text('Lorem ipsum.');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: i\'m a teapot (code 418).');
        $transport->send($mail);
    }

    public function testSendThrowsForAllSuppressed()
    {
        $client = new MockHttpClient(static fn (string $method, string $url, array $options): ResponseInterface => new JsonMockResponse([
            'message' => 'There are some warnings for your request.',
            'warnings' => [
                [
                    'type' => 'ALL_SUPPRESSED',
                ],
            ],
        ], [
            'http_code' => 202,
        ]));

        $transport = new MailerSendApiTransport('ACCESS_KEY', $client);

        $mail = new Email();
        $mail->subject('Test subject')
            ->to(new Address('test_to@example.com', 'Test to name'))
            ->from(new Address('test_from@example.com', 'Test from name'))
            ->text('Lorem ipsum.');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: There are some warnings for your request.');
        $transport->send($mail);
    }

    public function testSendThrowsForBadResponse()
    {
        $client = new MockHttpClient(static fn (string $method, string $url, array $options): ResponseInterface => new MockResponse('test', [
            'http_code' => 202,
        ]));

        $transport = new MailerSendApiTransport('ACCESS_KEY', $client);

        $mail = new Email();
        $mail->subject('Test subject')
            ->to(new Address('test_to@example.com', 'Test to name'))
            ->from(new Address('test_from@example.com', 'Test from name'))
            ->text('Lorem ipsum.');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: "test" (code 202).');
        $transport->send($mail);
    }

    public function testTrackingHeader()
    {
        $transport = new MailerSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailerSendApiTransport::class, 'getPayload');
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $enabledEmail = (new Email())->from('from@example.com')->to('to@example.com');
        $enabledEmail->getHeaders()->add(new TrackingHeader(opens: true, clicks: true));
        $enabledPayload = $method->invoke($transport, $enabledEmail, $envelope);
        $this->assertTrue($enabledPayload['settings']['track_opens']);
        $this->assertTrue($enabledPayload['settings']['track_clicks']);

        $disabledEmail = (new Email())->from('from@example.com')->to('to@example.com');
        $disabledEmail->getHeaders()->add(new TrackingHeader(opens: false, clicks: false));
        $disabledPayload = $method->invoke($transport, $disabledEmail, $envelope);
        $this->assertFalse($disabledPayload['settings']['track_opens']);
        $this->assertFalse($disabledPayload['settings']['track_clicks']);
    }

    public function testTrackingHeaderControlsOpensAndClicksIndependently()
    {
        $transport = new MailerSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailerSendApiTransport::class, 'getPayload');
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $email = (new Email())->from('from@example.com')->to('to@example.com');
        $email->getHeaders()->add(new TrackingHeader(clicks: false));
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('track_opens', $payload['settings']);
        $this->assertFalse($payload['settings']['track_clicks']);
    }

    public function testTagHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('tag1'));
        $email->getHeaders()->add(new TagHeader('tag2'));
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $transport = new MailerSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailerSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertSame(['tag1', 'tag2'], $payload['tags']);
    }

    public function testPayloadHasNoTagsWithoutTagHeader()
    {
        $email = new Email();
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $transport = new MailerSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailerSendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('tags', $payload);
    }

    public function testTagHeadersThrowsForTooManyTags()
    {
        $email = new Email();
        for ($i = 0; $i < 6; ++$i) {
            $email->getHeaders()->add(new TagHeader('tag'.$i));
        }
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $transport = new MailerSendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailerSendApiTransport::class, 'getPayload');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Too many "Symfony\Component\Mailer\Header\TagHeader" instances present in the email headers. MailerSend does not accept more than 5 tags on an email.');
        $method->invoke($transport, $email, $envelope);
    }
}
