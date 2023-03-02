<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailchimp\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillHttpTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MandrillHttpTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MandrillHttpTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            [
                new MandrillHttpTransport('KEY'),
                'mandrill+https://mandrillapp.com',
            ],
            [
                (new MandrillHttpTransport('KEY'))->setHost('example.com'),
                'mandrill+https://example.com',
            ],
            [
                (new MandrillHttpTransport('KEY'))->setHost('example.com')->setPort(99),
                'mandrill+https://example.com:99',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://mandrillapp.com/api/1.0/messages/send-raw.json', $url);

            $body = json_decode($options['body'], true);
            $message = $body['raw_message'];
            $this->assertSame('KEY', $body['key']);
            $this->assertSame('Fabien', $body['from_name']);
            $this->assertSame('fabpot@symfony.com', $body['from_email']);
            $this->assertSame('saif.gmati@symfony.com', $body['to'][0]);

            $this->assertStringContainsString('Subject: Hello!', $message);
            $this->assertStringContainsString('To: Saif Eddin <saif.gmati@symfony.com>', $message);
            $this->assertStringContainsString('From: Fabien <fabpot@symfony.com>', $message);
            $this->assertStringContainsString('Hello There!', $message);

            return new MockResponse(json_encode([['_id' => 'foobar']]), [
                'http_code' => 200,
            ]);
        });

        $transport = new MandrillHttpTransport('KEY', $client);

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
        $client = new MockHttpClient(fn (string $method, string $url, array $options): ResponseInterface => new MockResponse(json_encode(['status' => 'error', 'message' => 'i\'m a teapot', 'code' => 418]), [
            'http_code' => 418,
        ]));

        $transport = new MandrillHttpTransport('KEY', $client);

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
        $email->getHeaders()->add(new TagHeader('password-reset,user'));
        $email->getHeaders()->add(new TagHeader('another'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));

        $transport = new MandrillHttpTransport('key');
        $method = new \ReflectionMethod(MandrillHttpTransport::class, 'addMandrillHeaders');
        $method->invoke($transport, $email);

        $this->assertCount(3, $email->getHeaders()->toArray());
        $this->assertSame('foo: bar', $email->getHeaders()->get('FOO')->toString());
        $this->assertSame('X-MC-Tags: password-reset,user,another', $email->getHeaders()->get('X-MC-Tags')->toString());
        $this->assertSame('X-MC-Metadata: '.json_encode(['Color' => 'blue', 'Client-ID' => '12345']), $email->getHeaders()->get('X-MC-Metadata')->toString());
    }
}
