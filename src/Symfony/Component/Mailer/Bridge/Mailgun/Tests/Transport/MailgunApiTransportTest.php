<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailgun\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailgunApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MailgunApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public function getTransportData()
    {
        return [
            [
                new MailgunApiTransport('ACCESS_KEY', 'DOMAIN'),
                'mailgun+api://api.mailgun.net?domain=DOMAIN',
            ],
            [
                new MailgunApiTransport('ACCESS_KEY', 'DOMAIN', 'us-east-1'),
                'mailgun+api://api.us-east-1.mailgun.net?domain=DOMAIN',
            ],
            [
                (new MailgunApiTransport('ACCESS_KEY', 'DOMAIN'))->setHost('example.com'),
                'mailgun+api://example.com?domain=DOMAIN',
            ],
            [
                (new MailgunApiTransport('ACCESS_KEY', 'DOMAIN'))->setHost('example.com')->setPort(99),
                'mailgun+api://example.com:99?domain=DOMAIN',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $json = json_encode(['foo' => 'bar']);
        $deliveryTime = (new \DateTimeImmutable('2020-03-20 13:01:00'))->format(\DateTimeImmutable::RFC2822);

        $email = new Email();
        $email->getHeaders()->addTextHeader('h:X-Mailgun-Variables', $json);
        $email->getHeaders()->addTextHeader('h:foo', 'foo-value');
        $email->getHeaders()->addTextHeader('t:text', 'text-value');
        $email->getHeaders()->addTextHeader('o:deliverytime', $deliveryTime);
        $email->getHeaders()->addTextHeader('v:version', 'version-value');
        $email->getHeaders()->addTextHeader('template', 'template-value');
        $email->getHeaders()->addTextHeader('recipient-variables', 'recipient-variables-value');
        $email->getHeaders()->addTextHeader('amp-html', 'amp-html-value');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MailgunApiTransport('ACCESS_KEY', 'DOMAIN');
        $method = new \ReflectionMethod(MailgunApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('h:x-mailgun-variables', $payload);
        $this->assertEquals($json, $payload['h:x-mailgun-variables']);

        $this->assertArrayHasKey('h:foo', $payload);
        $this->assertEquals('foo-value', $payload['h:foo']);
        $this->assertArrayHasKey('t:text', $payload);
        $this->assertEquals('text-value', $payload['t:text']);
        $this->assertArrayHasKey('o:deliverytime', $payload);
        $this->assertEquals($deliveryTime, $payload['o:deliverytime']);
        $this->assertArrayHasKey('v:version', $payload);
        $this->assertEquals('version-value', $payload['v:version']);
        $this->assertArrayHasKey('template', $payload);
        $this->assertEquals('template-value', $payload['template']);
        $this->assertArrayHasKey('recipient-variables', $payload);
        $this->assertEquals('recipient-variables-value', $payload['recipient-variables']);
        $this->assertArrayHasKey('amp-html', $payload);
        $this->assertEquals('amp-html-value', $payload['amp-html']);
    }

    /**
     * @legacy
     */
    public function testPrefixHeaderWithH()
    {
        $json = json_encode(['foo' => 'bar']);
        $deliveryTime = (new \DateTimeImmutable('2020-03-20 13:01:00'))->format(\DateTimeImmutable::RFC2822);

        $email = new Email();
        $email->getHeaders()->addTextHeader('h:bar', 'bar-value');

        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MailgunApiTransport('ACCESS_KEY', 'DOMAIN');
        $method = new \ReflectionMethod(MailgunApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('h:bar', $payload, 'We should prefix headers with "h:" to keep BC');
        $this->assertEquals('bar-value', $payload['h:bar']);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.us-east-1.mailgun.net:8984/v3/symfony/messages', $url);
            $this->assertStringContainsString('Basic YXBpOkFDQ0VTU19LRVk=', $options['headers'][2] ?? $options['request_headers'][1]);

            $content = '';
            while ($chunk = $options['body']()) {
                $content .= $chunk;
            }

            $this->assertStringContainsString('Hello!', $content);
            $this->assertStringContainsString('Saif Eddin <saif.gmati@symfony.com>', $content);
            $this->assertStringContainsString('Fabien <fabpot@symfony.com>', $content);
            $this->assertStringContainsString('Hello There!', $content);

            return new MockResponse(json_encode(['id' => 'foobar']), [
                'http_code' => 200,
            ]);
        });
        $transport = new MailgunApiTransport('ACCESS_KEY', 'symfony', 'us-east-1', $client);
        $transport->setPort(8984);

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
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mailgun.net:8984/v3/symfony/messages', $url);
            $this->assertStringContainsStringIgnoringCase('Authorization: Basic YXBpOkFDQ0VTU19LRVk=', $options['headers'][2] ?? $options['request_headers'][1]);

            return new MockResponse(json_encode(['message' => 'i\'m a teapot']), [
                'http_code' => 418,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]);
        });
        $transport = new MailgunApiTransport('ACCESS_KEY', 'symfony', 'us', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: i\'m a teapot (code 418).');
        $transport->send($mail);
    }

    public function testSendThrowsForErrorResponseWithContentTypeTextHtml()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.mailgun.net:8984/v3/symfony/messages', $url);
            $this->assertStringContainsStringIgnoringCase('Authorization: Basic YXBpOkFDQ0VTU19LRVk=', $options['headers'][2] ?? $options['request_headers'][1]);

            // NOTE: Mailgun API does this even if "Accept" request header value is "application/json".
            return new MockResponse('Forbidden', [
                'http_code' => 401,
                'response_headers' => [
                    'content-type' => 'text/html',
                ],
            ]);
        });
        $transport = new MailgunApiTransport('ACCESS_KEY', 'symfony', 'us', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: Forbidden (code 401).');
        $transport->send($mail);
    }

    public function testTagAndMetadataHeaders()
    {
        $json = json_encode(['foo' => 'bar']);
        $email = new Email();
        $email->getHeaders()->addTextHeader('h:X-Mailgun-Variables', $json);
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MailgunApiTransport('ACCESS_KEY', 'DOMAIN');
        $method = new \ReflectionMethod(MailgunApiTransport::class, 'getPayload');
        $method->setAccessible(true);
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('h:x-mailgun-variables', $payload);
        $this->assertEquals($json, $payload['h:x-mailgun-variables']);
        $this->assertArrayHasKey('o:tag', $payload);
        $this->assertSame('password-reset', $payload['o:tag']);
        $this->assertArrayHasKey('v:Color', $payload);
        $this->assertSame('blue', $payload['v:Color']);
        $this->assertArrayHasKey('v:Client-ID', $payload);
        $this->assertSame('12345', $payload['v:Client-ID']);
    }
}
