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
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailgunHttpTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MailgunHttpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            [
                new MailgunHttpTransport('ACCESS_KEY', 'DOMAIN'),
                'mailgun+https://api.mailgun.net?domain=DOMAIN',
            ],
            [
                new MailgunHttpTransport('ACCESS_KEY', 'DOMAIN', 'us-east-1'),
                'mailgun+https://api.us-east-1.mailgun.net?domain=DOMAIN',
            ],
            [
                (new MailgunHttpTransport('ACCESS_KEY', 'DOMAIN'))->setHost('example.com'),
                'mailgun+https://example.com?domain=DOMAIN',
            ],
            [
                (new MailgunHttpTransport('ACCESS_KEY', 'DOMAIN'))->setHost('example.com')->setPort(99),
                'mailgun+https://example.com:99?domain=DOMAIN',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.us-east-1.mailgun.net:8984/v3/symfony/messages.mime', $url);
            $this->assertStringContainsString('Basic YXBpOkFDQ0VTU19LRVk=', $options['headers'][2] ?? $options['request_headers'][1]);

            $content = '';
            while ($chunk = $options['body']()) {
                $content .= $chunk;
            }

            $this->assertStringContainsString('Subject: Hello!', $content);
            $this->assertStringContainsString('To: Saif Eddin <saif.gmati@symfony.com>', $content);
            $this->assertStringContainsString('From: Fabien <fabpot@symfony.com>', $content);
            $this->assertStringContainsString('Hello There!', $content);

            return new MockResponse(json_encode(['id' => 'foobar']), [
                'http_code' => 200,
            ]);
        });
        $transport = new MailgunHttpTransport('ACCESS_KEY', 'symfony', 'us-east-1', $client);
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
            $this->assertSame('https://api.mailgun.net:8984/v3/symfony/messages.mime', $url);
            $this->assertStringContainsString('Basic YXBpOkFDQ0VTU19LRVk=', $options['headers'][2] ?? $options['request_headers'][1]);

            return new MockResponse(json_encode(['message' => 'i\'m a teapot']), [
                'http_code' => 418,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]);
        });
        $transport = new MailgunHttpTransport('ACCESS_KEY', 'symfony', 'us', $client);
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

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new TagHeader('product-name'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));

        $transport = new MailgunHttpTransport('key', 'domain');
        $method = new \ReflectionMethod(MailgunHttpTransport::class, 'addMailgunHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(4, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('foo')->toString());
        $tagHeaders = iterator_to_array($email->getHeaders()->all('X-Mailgun-Tag'));
        $this->assertCount(2, $tagHeaders);
        $this->assertSame('X-Mailgun-Tag: password-reset', $tagHeaders[0]->toString());
        $this->assertSame('X-Mailgun-Tag: product-name', $tagHeaders[1]->toString());
        $this->assertSame('X-Mailgun-Variables: '.json_encode(['Color' => 'blue', 'Client-ID' => '12345']), $email->getHeaders()->get('X-Mailgun-Variables')->toString());
    }
}
