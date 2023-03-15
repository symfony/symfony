<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\OhMySmtp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\OhMySmtp\Transport\OhMySmtpApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @group legacy
 */
final class OhMySmtpApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(OhMySmtpApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new OhMySmtpApiTransport('KEY'),
                'ohmysmtp+api://app.ohmysmtp.com/api/v1',
            ],
            [
                (new OhMySmtpApiTransport('KEY'))->setHost('example.com'),
                'ohmysmtp+api://example.com',
            ],
            [
                (new OhMySmtpApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'ohmysmtp+api://example.com:99',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new OhMySmtpApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(OhMySmtpApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('Headers', $payload);
        $this->assertCount(1, $payload['Headers']);

        $this->assertEquals(['Name' => 'foo', 'Value' => 'bar'], $payload['Headers'][0]);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://app.ohmysmtp.com/api/v1/send', $url);
            $this->assertStringContainsStringIgnoringCase('OhMySMTP-Server-Token: KEY', $options['headers'][1] ?? $options['request_headers'][1]);

            $body = json_decode($options['body'], true);
            $this->assertSame('"Fabien" <fabpot@symfony.com>', $body['from']);
            $this->assertSame('"Saif Eddin" <saif.gmati@symfony.com>', $body['to']);
            $this->assertSame('Hello!', $body['subject']);
            $this->assertSame('Hello There!', $body['textbody']);

            return new MockResponse(json_encode(['id' => 'foobar', 'status' => 'pending']), [
                'http_code' => 200,
            ]);
        });

        $transport = new OhMySmtpApiTransport('KEY', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(static fn (string $method, string $url, array $options): ResponseInterface => new MockResponse(json_encode(['error' => 'i\'m a teapot']), [
            'http_code' => 418,
            'response_headers' => [
                'content-type' => 'application/json',
            ],
        ]));
        $transport = new OhMySmtpApiTransport('KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: {"error":"i\'m a teapot"}');
        $transport->send($mail);
    }

    public function testSendThrowsForMultipleErrorResponses()
    {
        $client = new MockHttpClient(static fn (string $method, string $url, array $options): ResponseInterface => new MockResponse(json_encode(['errors' => ['to' => 'undefined field']]), [
            'http_code' => 418,
            'response_headers' => [
                'content-type' => 'application/json',
            ],
        ]));
        $transport = new OhMySmtpApiTransport('KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: {"errors":{"to":"undefined field"}}');
        $transport->send($mail);
    }

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new TagHeader('2nd-tag'));

        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new OhMySmtpApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(OhMySmtpApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('Headers', $payload);
        $this->assertArrayHasKey('tags', $payload);

        $this->assertSame(['password-reset', '2nd-tag'], $payload['tags']);
    }
}
