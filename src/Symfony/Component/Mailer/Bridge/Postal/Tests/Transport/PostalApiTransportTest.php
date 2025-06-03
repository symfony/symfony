<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postal\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Postal\Transport\PostalApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PostalApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(PostalApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new PostalApiTransport('TOKEN', 'postal.localhost'),
                'postal+api://postal.localhost',
            ],
            [
                (new PostalApiTransport('TOKEN', 'postal.localhost'))->setPort(99),
                'postal+api://postal.localhost:99',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://postal.localhost:8984/api/v1/send/message', $url);
            $this->assertStringContainsString('X-Server-API-Key: TOKEN', $options['headers'][0] ?? $options['request_headers'][0]);

            $body = json_decode($options['body'], true);
            $this->assertSame('fabpot@symfony.com', $body['from']);
            $this->assertSame('saif.gmati@symfony.com', $body['to'][0]);
            $this->assertSame('Hello!', $body['subject']);
            $this->assertSame('Hello There!', $body['plain_body']);
            $this->assertSame('<h1>Hello There!</h1>', $body['html_body']);
            $this->assertCount(1, $body['attachments']);
            $this->assertSame('attachment.txt', $body['attachments'][0]['name']);
            $this->assertSame('text/plain', $body['attachments'][0]['content_type']);
            $this->assertSame(base64_encode('some attachment'), $body['attachments'][0]['data']);
            $this->assertSame('foo@bar.fr', $body['reply_to']);

            return new JsonMockResponse(['message_id' => 'foobar'], [
                'http_code' => 200,
            ]);
        });
        $transport = new PostalApiTransport('TOKEN', 'postal.localhost', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->replyTo(new Address('foo@bar.fr', 'Foo'))
            ->text('Hello There!')
            ->html('<h1>Hello There!</h1>')
            ->addPart(new DataPart('some attachment', 'attachment.txt', 'text/plain'));

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            return new JsonMockResponse(['message' => 'i\'m a teapot'], [
                'http_code' => 418,
            ]);
        });
        $transport = new PostalApiTransport('TOKEN', 'postal.localhost', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: i\'m a teapot (code 418).');
        $transport->send($mail);
    }
}
