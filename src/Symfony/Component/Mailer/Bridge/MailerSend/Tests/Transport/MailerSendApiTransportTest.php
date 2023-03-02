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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\MailerSend\Transport\MailerSendApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailerSendApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
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

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            return new MockResponse(json_encode(['message' => 'i\'m a teapot']), [
                'http_code' => 418,
            ]);
        });

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
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            return new MockResponse(json_encode([
                'message' => 'There are some warnings for your request.',
                'warnings' => [
                    [
                        'type' => 'ALL_SUPPRESSED',
                    ],
                ],
            ], \JSON_THROW_ON_ERROR), [
                'http_code' => 202,
            ]);
        });

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
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            return new MockResponse('test', [
                'http_code' => 202,
            ]);
        });

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
}
