<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Scaleway\Transport\ScalewayApiTransport;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ScalewayApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(ScalewayApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            [
                new ScalewayApiTransport('PROJECT_ID', 'TOKEN'),
                'scaleway+api://api.scaleway.com@PROJECT_ID',
            ],
            [
                new ScalewayApiTransport('PROJECT_ID', 'TOKEN', 'fr-par'),
                'scaleway+api://api.scaleway.com@PROJECT_ID?region=fr-par',
            ],
            [
                (new ScalewayApiTransport('PROJECT_ID', 'TOKEN'))->setHost('example.com'),
                'scaleway+api://example.com@PROJECT_ID',
            ],
            [
                (new ScalewayApiTransport('PROJECT_ID', 'TOKEN'))->setHost('example.com')->setPort(99),
                'scaleway+api://example.com:99@PROJECT_ID',
            ],
        ];
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.scaleway.com:8984/transactional-email/v1alpha1/regions/fr-par/emails', $url);
            $this->assertStringContainsString('X-Auth-Token: TOKEN', $options['headers'][0] ?? $options['request_headers'][0]);

            $body = json_decode($options['body'], true);
            $this->assertSame(['email' => 'fabpot@symfony.com', 'name' => 'Fabien'], $body['from']);
            $this->assertSame(['email' => 'saif.gmati@symfony.com', 'name' => 'Saif Eddin'], $body['to'][0]);
            $this->assertSame('Hello!', $body['subject']);
            $this->assertSame('Hello There!', $body['text']);
            $this->assertCount(1, $body['attachments']);
            $this->assertSame('attachment.txt', $body['attachments'][0]['name']);
            $this->assertSame('text/plain', $body['attachments'][0]['type']);
            $this->assertSame(base64_encode('some attachment'), $body['attachments'][0]['content']);
            $this->assertSame('Reply-To', $body['additional_headers'][0]['key']);
            $this->assertStringContainsString('foo@bar.fr', $body['additional_headers'][0]['value']);

            return new JsonMockResponse(['emails' => [['message_id' => 'foobar']]], [
                'http_code' => 200,
            ]);
        });
        $transport = new ScalewayApiTransport('PROJECT_ID', 'TOKEN', 'fr-par', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->replyTo(new Address('foo@bar.fr', 'Foo'))
            ->text('Hello There!')
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
        $transport = new ScalewayApiTransport('PROJECT_ID', 'TOKEN', 'fr-par', $client);

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
