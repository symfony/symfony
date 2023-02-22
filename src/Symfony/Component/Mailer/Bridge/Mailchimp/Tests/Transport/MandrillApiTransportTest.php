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
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MandrillApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MandrillApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            [
                new MandrillApiTransport('KEY'),
                'mandrill+api://mandrillapp.com',
            ],
            [
                (new MandrillApiTransport('KEY'))->setHost('example.com'),
                'mandrill+api://example.com',
            ],
            [
                (new MandrillApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'mandrill+api://example.com:99',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MandrillApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MandrillApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('headers', $payload['message']);
        $this->assertCount(1, $payload['message']['headers']);
        $this->assertEquals('bar', $payload['message']['headers']['foo']);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://mandrillapp.com/api/1.0/messages/send.json', $url);

            $body = json_decode($options['body'], true);
            $message = $body['message'];
            $this->assertSame('KEY', $body['key']);
            $this->assertSame('Fabien', $message['from_name']);
            $this->assertSame('fabpot@symfony.com', $message['from_email']);
            $this->assertSame('Saif Eddin', $message['to'][0]['name']);
            $this->assertSame('saif.gmati@symfony.com', $message['to'][0]['email']);
            $this->assertSame('Hello!', $message['subject']);
            $this->assertSame('Hello There!', $message['text']);

            return new MockResponse(json_encode([['_id' => 'foobar']]), [
                'http_code' => 200,
            ]);
        });

        $transport = new MandrillApiTransport('KEY', $client);

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

        $transport = new MandrillApiTransport('KEY', $client);

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
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MandrillApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MandrillApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayNotHasKey('headers', $payload['message']);
        $this->assertArrayHasKey('tags', $payload['message']);
        $this->assertSame(['password-reset'], $payload['message']['tags']);
        $this->assertArrayHasKey('metadata', $payload['message']);
        $this->assertSame(['Color' => 'blue', 'Client-ID' => '12345'], $payload['message']['metadata']);
    }

    public function testCanHaveMultipleTags()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('password-reset,user'));
        $email->getHeaders()->add(new TagHeader('another'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MandrillApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MandrillApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayNotHasKey('headers', $payload['message']);
        $this->assertArrayHasKey('tags', $payload['message']);
        $this->assertSame(['password-reset', 'user', 'another'], $payload['message']['tags']);
    }
}
