<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendinblue\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Sendinblue\Transport\SendinblueApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SendinblueApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SendinblueApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        yield [
            new SendinblueApiTransport('ACCESS_KEY'),
            'sendinblue+api://api.sendinblue.com',
        ];

        yield [
            (new SendinblueApiTransport('ACCESS_KEY'))->setHost('example.com'),
            'sendinblue+api://example.com',
        ];

        yield [
            (new SendinblueApiTransport('ACCESS_KEY'))->setHost('example.com')->setPort(99),
            'sendinblue+api://example.com:99',
        ];
    }

    public function testCustomHeader()
    {
        $params = ['param1' => 'foo', 'param2' => 'bar'];
        $json = json_encode(['"custom_header_1' => 'custom_value_1']);

        $email = new Email();
        $email->getHeaders()
            ->add(new MetadataHeader('custom', $json))
            ->add(new TagHeader('TagInHeaders'))
            ->addTextHeader('templateId', 1)
            ->addParameterizedHeader('params', 'params', $params)
            ->addTextHeader('foo', 'bar')
        ;
        $envelope = new Envelope(new Address('alice@system.com', 'Alice'), [new Address('bob@system.com', 'Bob')]);

        $transport = new SendinblueApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SendinblueApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('X-Mailin-Custom', $payload['headers']);
        $this->assertEquals($json, $payload['headers']['X-Mailin-Custom']);

        $this->assertArrayHasKey('tags', $payload);
        $this->assertEquals('TagInHeaders', current($payload['tags']));
        $this->assertArrayHasKey('templateId', $payload);
        $this->assertEquals(1, $payload['templateId']);
        $this->assertArrayHasKey('params', $payload);
        $this->assertEquals('foo', $payload['params']['param1']);
        $this->assertEquals('bar', $payload['params']['param2']);
        $this->assertArrayHasKey('foo', $payload['headers']);
        $this->assertEquals('bar', $payload['headers']['foo']);
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.sendinblue.com:8984/v3/smtp/email', $url);
            $this->assertStringContainsString('Accept: */*', $options['headers'][2] ?? $options['request_headers'][1]);

            return new MockResponse(json_encode(['message' => 'i\'m a teapot']), [
                'http_code' => 418,
                'response_headers' => [
                    'content-type' => 'application/json',
                ],
            ]);
        });

        $transport = new SendinblueApiTransport('ACCESS_KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!')
        ;

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: i\'m a teapot (code 418).');
        $transport->send($mail);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.sendinblue.com:8984/v3/smtp/email', $url);
            $this->assertStringContainsString('Accept: */*', $options['headers'][2] ?? $options['request_headers'][1]);

            return new MockResponse(json_encode(['messageId' => 'foobar']), [
                'http_code' => 201,
            ]);
        });

        $transport = new SendinblueApiTransport('ACCESS_KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello here!')
            ->html('Hello there!')
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
            ->addReplyTo('foo@bar.fr')
            ->addPart(new DataPart('body'))
        ;

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }
}
