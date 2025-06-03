<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Mailtrap\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Mailtrap\Transport\MailtrapApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class MailtrapApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(MailtrapApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new MailtrapApiTransport('KEY'),
                'mailtrap+api://send.api.mailtrap.io',
            ],
            [
                (new MailtrapApiTransport('KEY'))->setHost('example.com'),
                'mailtrap+api://example.com',
            ],
            [
                (new MailtrapApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'mailtrap+api://example.com:99',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MailtrapApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailtrapApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('headers', $payload);
        $this->assertSame(['foo' => 'bar'], $payload['headers']);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://send.api.mailtrap.io/api/send', $url);

            $body = json_decode($options['body'], true);
            $this->assertSame(['email' => 'fabpot@symfony.com', 'name' => 'Fabien'], $body['from']);
            $this->assertSame([['email' => 'kevin@symfony.com', 'name' => 'Kevin']], $body['to']);
            $this->assertSame('Hello!', $body['subject']);
            $this->assertSame('Hello There!', $body['text']);

            return new JsonMockResponse([], [
                'http_code' => 200,
            ]);
        });

        $transport = new MailtrapApiTransport('KEY', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('kevin@symfony.com', 'Kevin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $transport->send($mail);
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(static fn (string $method, string $url, array $options): ResponseInterface => new JsonMockResponse(['errors' => ['i\'m a teapot']], [
            'http_code' => 418,
        ]));
        $transport = new MailtrapApiTransport('KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('kevin@symfony.com', 'Kevin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send email: "i\'m a teapot" (status code 418).');
        $transport->send($mail);
    }

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MailtrapApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailtrapApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('Headers', $payload);
        $this->assertArrayHasKey('category', $payload);
        $this->assertArrayHasKey('custom_variables', $payload);

        $this->assertSame('password-reset', $payload['category']);
        $this->assertSame(['Color' => 'blue', 'Client-ID' => '12345'], $payload['custom_variables']);
    }

    public function testMultipleTagsAreNotAllowed()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('tag1'));
        $email->getHeaders()->add(new TagHeader('tag2'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new MailtrapApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(MailtrapApiTransport::class, 'getPayload');

        $this->expectException(TransportException::class);

        $method->invoke($transport, $email, $envelope);
    }
}
