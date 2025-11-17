<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\MicrosoftGraph\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Tests\TokenManagerMock;
use Symfony\Component\Mailer\Bridge\MicrosoftGraph\Transport\MicrosoftGraphApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MicrosoftGraphApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(MicrosoftGraphApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new MicrosoftGraphApiTransport('graph.ms.com', new TokenManagerMock(), false),
                'microsoftgraph+api://graph.ms.com',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://graph/v1.0/users/fabpot@symfony.com/sendMail', $url);

            $this->assertNotEmpty($options['normalized_headers']['authorization']);

            $message = json_decode($options['body'], true)['message'];

            $this->assertSame('Fabien', $message['sender']['emailAddress']['name']);
            $this->assertSame('fabpot@symfony.com', $message['sender']['emailAddress']['address']);

            $this->assertSame('Hello!', $message['subject']);

            $mailBody = $message['body'];
            $this->assertSame('Hello There!', $mailBody['content']);
            $this->assertSame('text', $mailBody['contentType']);

            $this->assertSame('normal', $message['importance']);

            $this->assertCount(1, $message['toRecipients']);
            $this->assertSame('Bob', $message['toRecipients'][0]['emailAddress']['name']);
            $this->assertSame('bob@symfony.com', $message['toRecipients'][0]['emailAddress']['address']);

            $this->assertCount(1, $message['replyTo']);
            $this->assertArrayNotHasKey('name', $message['replyTo'][0]['emailAddress']);
            $this->assertSame('bob@symfony.com', $message['replyTo'][0]['emailAddress']['address']);

            $attachment = $message['attachments'][0];
            $this->assertSame('#microsoft.graph.fileAttachment', $attachment['@odata.type']);
            $this->assertSame('Hello There!', $attachment['name']);
            $this->assertSame(base64_encode('content'), $attachment['contentBytes']);
            $this->assertSame('text/plain', $attachment['contentType']);
            $this->assertArrayNotHasKey('contentId', $attachment);
            $this->assertArrayNotHasKey('isInline', $attachment);

            return new MockResponse('', ['http_code' => 202]);
        });

        $transport = new MicrosoftGraphApiTransport('graph', new TokenManagerMock(), false, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->replyTo('bob@symfony.com')
            ->text('Hello There!')
            ->attach('content', 'Hello There!', 'text/plain');

        $transport->send($mail);
    }

    public function testCcBcc()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://graph/v1.0/users/fabpot@symfony.com/sendMail', $url);

            $message = json_decode($options['body'], true)['message'];

            $this->assertCount(1, $message['ccRecipients']);
            $this->assertSame('Alice', $message['ccRecipients'][0]['emailAddress']['name']);
            $this->assertSame('alice-cc@symfony.com', $message['ccRecipients'][0]['emailAddress']['address']);

            $this->assertCount(1, $message['bccRecipients']);
            $this->assertSame('Alice', $message['bccRecipients'][0]['emailAddress']['name']);
            $this->assertSame('alice-bcc@symfony.com', $message['bccRecipients'][0]['emailAddress']['address']);

            return new MockResponse('', ['http_code' => 202]);
        });

        $transport = new MicrosoftGraphApiTransport('graph', new TokenManagerMock(), false, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->cc(new Address('alice-cc@symfony.com', 'Alice'))
            ->bcc(new Address('alice-bcc@symfony.com', 'Alice'))
            ->text('Hello world');

        $transport->send($mail);
    }

    public function testHtmlBody()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://graph/v1.0/users/fabpot@symfony.com/sendMail', $url);

            $message = json_decode($options['body'], true)['message'];

            $mailBody = $message['body'];
            $this->assertSame('<html>Hello There!</html>', $mailBody['content']);
            $this->assertSame('html', $mailBody['contentType']);

            return new MockResponse('', ['http_code' => 202]);
        });

        $transport = new MicrosoftGraphApiTransport('graph', new TokenManagerMock(), false, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->html('<html>Hello There!</html>');

        $transport->send($mail);
    }

    public function testEmbeddedAttachment()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://graph/v1.0/users/fabpot@symfony.com/sendMail', $url);

            $message = json_decode($options['body'], true)['message'];

            $attachment = $message['attachments'][0];
            $this->assertSame('Embedded content', $attachment['name']);
            $this->assertSame('Y29udGVudA==', $attachment['contentBytes']);
            $this->assertMatchesRegularExpression('/^[0-9a-f]{32}@symfony$/', $attachment['contentId']);
            $this->assertTrue($attachment['isInline']);

            return new MockResponse('', ['http_code' => 202]);
        });

        $transport = new MicrosoftGraphApiTransport('graph', new TokenManagerMock(), false, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->embed('content', 'Embedded content', 'text/plain');

        $transport->send($mail);
    }

    public function testRespectsNoSaveParameter()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://graph/v1.0/users/fabpot@symfony.com/sendMail', $url);

            $body = json_decode($options['body'], true);

            $this->assertFalse($body['saveToSentItems']);

            return new MockResponse('', ['http_code' => 202]);
        });

        $transport = new MicrosoftGraphApiTransport('graph', new TokenManagerMock(), true, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $transport->send($mail);
    }

    public function testCustomHeader()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://graph/v1.0/users/fabpot@symfony.com/sendMail', $url);

            $message = json_decode($options['body'], true)['message'];

            $headers = $message['internetMessageHeaders'];
            $this->assertCount(1, $headers);
            $this->assertSame('X-Something', $headers[0]['name']);
            $this->assertSame('HeaderValue', $headers[0]['value']);

            return new MockResponse('', ['http_code' => 202]);
        });

        $transport = new MicrosoftGraphApiTransport('graph', new TokenManagerMock(), true, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');
        $mail->getHeaders()->addHeader('X-Something', 'HeaderValue');

        $transport->send($mail);
    }

    #[DataProvider('importanceProvider')]
    public function testImportance(string $expected, int $priority)
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($expected): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://graph/v1.0/users/fabpot@symfony.com/sendMail', $url);

            $message = json_decode($options['body'], true)['message'];

            $this->assertSame($expected, $message['importance']);

            return new MockResponse('', ['http_code' => 202]);
        });

        $transport = new MicrosoftGraphApiTransport('graph', new TokenManagerMock(), true, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!')
            ->priority($priority);

        $transport->send($mail);
    }

    public static function importanceProvider(): iterable
    {
        yield ['high', Email::PRIORITY_HIGHEST];
        yield ['high', Email::PRIORITY_HIGH];
        yield ['normal', Email::PRIORITY_NORMAL];
        yield ['low', Email::PRIORITY_LOW];
        yield ['low', Email::PRIORITY_LOWEST];
    }

    public function testNonSuccessCodeThrown()
    {
        $client = new MockHttpClient(fn (): ResponseInterface => new MockResponse('', ['http_code' => 503]));

        $transport = new MicrosoftGraphApiTransport('graph', new TokenManagerMock(), true, $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');
        $mail->getHeaders()->addHeader('X-Prio', 1);

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessageMatches('/^Unable to send an email/');

        $transport->send($mail);
    }
}
