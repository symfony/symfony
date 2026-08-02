<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Google\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Google\TokenManager;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GmailApiTransportTest extends TestCase
{
    #[DataProvider('getTransportData')]
    public function testToString(GmailApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new GmailApiTransport(self::createTokenManager(), 'user@example.com'),
                'gmail+api://user@example.com',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://gmail.googleapis.com/gmail/v1/users/fabpot@symfony.com/messages/send', $url);

            $this->assertNotEmpty($options['normalized_headers']['authorization']);
            $this->assertStringContainsString('Bearer ACCESSTOKEN', $options['normalized_headers']['authorization'][0]);

            $body = json_decode($options['body'], true);
            $this->assertArrayHasKey('raw', $body);

            // Decode the raw message and verify basic structure
            $rawMessage = $this->base64UrlDecode($body['raw']);
            $this->assertStringContainsString('From: Fabien <fabpot@symfony.com>', $rawMessage);
            $this->assertStringContainsString('To: Bob <bob@symfony.com>', $rawMessage);
            $this->assertStringContainsString('Subject: Hello!', $rawMessage);
            $this->assertStringContainsString('Hello There!', $rawMessage);

            return new JsonMockResponse(['id' => 'message-id-123', 'threadId' => 'thread-id-456'], ['http_code' => 200]);
        });

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $sentMessage = $transport->send($mail);
        $this->assertSame('message-id-123', $sentMessage->getMessageId());
    }

    public function testSendWithCc()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $rawMessage = $this->base64UrlDecode($body['raw']);

            $this->assertStringContainsString('Cc: Alice <alice@symfony.com>', $rawMessage);

            return new JsonMockResponse(['id' => 'msg-123'], ['http_code' => 200]);
        });

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->cc(new Address('alice@symfony.com', 'Alice'))
            ->text('Hello There!');

        $transport->send($mail);
    }

    public function testSendWithBcc()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $rawMessage = $this->base64UrlDecode($body['raw']);

            // the Gmail API takes the recipients from the raw message headers, honors
            // the "Bcc" header for delivery and strips it from the delivered message
            $this->assertStringContainsString('Bcc: ', $rawMessage);
            $this->assertStringContainsString('secret@symfony.com', $rawMessage);

            return new JsonMockResponse(['id' => 'msg-123'], ['http_code' => 200]);
        });

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->bcc(new Address('secret@symfony.com', 'Secret'))
            ->text('Hello There!');

        $transport->send($mail);
    }

    public function testSendHtmlEmail()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $rawMessage = $this->base64UrlDecode($body['raw']);

            $this->assertStringContainsString('Content-Type: text/html', $rawMessage);
            $this->assertStringContainsString('<html>Hello World!</html>', $rawMessage);

            return new JsonMockResponse(['id' => 'msg-123'], ['http_code' => 200]);
        });

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('HTML Email')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->html('<html>Hello World!</html>');

        $transport->send($mail);
    }

    public function testSendWithAttachment()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $rawMessage = $this->base64UrlDecode($body['raw']);

            $this->assertStringContainsString('Content-Disposition: attachment', $rawMessage);
            $this->assertStringContainsString('filename=test.txt', $rawMessage);

            return new JsonMockResponse(['id' => 'msg-123'], ['http_code' => 200]);
        });

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('Email with attachment')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('See attached file.')
            ->attach('file content', 'test.txt', 'text/plain');

        $transport->send($mail);
    }

    public function testNonSuccessCodeThrown()
    {
        $client = new MockHttpClient(static fn (): ResponseInterface => new MockResponse('{"error": {"message": "Quota exceeded"}}', ['http_code' => 429]));

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessageMatches('/^Unable to send email via Gmail API/');

        $transport->send($mail);
    }

    public function testCustomEnvelopeRecipientsAreDelivered()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $body = json_decode($options['body'], true);
            $rawMessage = $this->base64UrlDecode($body['raw']);

            $this->assertStringContainsString('To: Bob <bob@symfony.com>', $rawMessage);
            $this->assertStringContainsString('Bcc: ', $rawMessage);
            $this->assertStringContainsString('archive@symfony.com', $rawMessage);

            return new JsonMockResponse(['id' => 'msg-123'], ['http_code' => 200]);
        });

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $envelope = new Envelope(new Address('fabpot@symfony.com'), [new Address('bob@symfony.com'), new Address('archive@symfony.com')]);

        $transport->send($mail, $envelope);
    }

    public function testEnvelopeRecipientsReplaceTheHeaderOnes()
    {
        $rawMessage = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$rawMessage): ResponseInterface {
            if (!str_contains($url, 'oauth2')) {
                $rawMessage = $this->base64UrlDecode(json_decode($options['body'], true)['raw']);
            }

            return new JsonMockResponse(['id' => 'msg-123', 'access_token' => 'ACCESSTOKEN', 'expires_in' => 3600], ['http_code' => 200]);
        });

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->cc(new Address('boss@symfony.com', 'Boss'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $transport->send($mail, new Envelope(new Address('fabpot@symfony.com'), [new Address('staging@symfony.com')]));

        $this->assertStringNotContainsString('bob@symfony.com', $rawMessage);
        $this->assertStringNotContainsString('boss@symfony.com', $rawMessage);
        $this->assertStringContainsString('Bcc: staging@symfony.com', $rawMessage);
    }

    public function testTheSentMessageIdIsTheOneOfTheRawMessage()
    {
        $rawMessage = null;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$rawMessage): ResponseInterface {
            if (!str_contains($url, 'oauth2')) {
                $rawMessage = $this->base64UrlDecode(json_decode($options['body'], true)['raw']);
            }

            return new JsonMockResponse(['id' => 'msg-123', 'access_token' => 'ACCESSTOKEN', 'expires_in' => 3600], ['http_code' => 200]);
        });

        $transport = new GmailApiTransport(self::createTokenManager(), 'fabpot@symfony.com', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('bob@symfony.com', 'Bob'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $sentMessage = $transport->send($mail);

        preg_match('/^Message-ID: (.*)$/mi', $rawMessage, $sent);
        preg_match('/^Message-ID: (.*)$/mi', $sentMessage->toString(), $reported);

        $this->assertSame(trim($reported[1]), trim($sent[1]));
    }

    private static function createTokenManager(): TokenManager
    {
        return new TokenManager(
            'service@example.iam.gserviceaccount.com',
            file_get_contents(__DIR__.'/../Fixtures/private_key.pem'),
            'user@example.com',
            new MockHttpClient(new JsonMockResponse(['access_token' => 'ACCESSTOKEN', 'expires_in' => 3600])),
        );
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
