<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sweego\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Sweego\Transport\SweegoApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SweegoApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(SweegoApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): \Generator
    {
        yield [
            new SweegoApiTransport('ACCESS_KEY'),
            'sweego+api://api.sweego.io',
        ];

        yield [
            (new SweegoApiTransport('ACCESS_KEY'))->setHost('example.com'),
            'sweego+api://example.com',
        ];

        yield [
            (new SweegoApiTransport('ACCESS_KEY'))->setHost('example.com')->setPort(99),
            'sweego+api://example.com:99',
        ];
    }

    public function testCustomHeader()
    {
        $params = ['param1' => 'foo', 'param2' => 'bar'];
        $json = json_encode(['custom_header_1' => 'custom_value_1']);

        $email = new Email();
        $email->getHeaders()
            ->add(new MetadataHeader('custom', $json))
            ->addTextHeader('templateId', 1)
            ->addParameterizedHeader('params', 'params', $params)
            ->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com', 'Alice'), [new Address('bob@system.com', 'Bob')]);

        $transport = new SweegoApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(SweegoApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('X-Metadata-custom', $payload['headers']);
        $this->assertEquals($json, $payload['headers']['X-Metadata-custom']);
        $this->assertArrayHasKey('templateId', $payload['headers']);
        $this->assertEquals('1', $payload['headers']['templateId']);
        $this->assertArrayHasKey('params', $payload['headers']);
        $this->assertEquals('params; param1=foo; param2=bar', $payload['headers']['params']);
        $this->assertArrayHasKey('foo', $payload['headers']);
        $this->assertEquals('bar', $payload['headers']['foo']);
    }

    public function testSendThrowsForErrorResponse()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.sweego.io:8984/send', $url);
            $this->assertStringContainsString('Accept: */*', $options['headers'][2] ?? $options['request_headers'][1]);

            return new JsonMockResponse(['message' => 'i\'m a teapot'], [
                'http_code' => 418,
            ]);
        });

        $transport = new SweegoApiTransport('ACCESS_KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('tony.stark@marvel.com', 'Tony Stark'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!');

        $this->expectException(HttpTransportException::class);
        $this->expectExceptionMessage('Unable to send an email: {"message":"i\'m a teapot"} (code 418).');
        $transport->send($mail);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.sweego.io:8984/send', $url);
            $this->assertStringContainsString('Accept: */*', $options['headers'][2] ?? $options['request_headers'][1]);

            return new JsonMockResponse(['transaction_id' => 'foobar'], [
                'http_code' => 200,
            ]);
        });

        $transport = new SweegoApiTransport('ACCESS_KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('tony.stark@marvel.com', 'Tony Stark'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello here!')
            ->html('Hello there!')
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
        ;

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }

    /**
     * IDN (internationalized domain names) like kältetechnik-xyz.de need to be transformed to ACE
     * (ASCII Compatible Encoding) e.g.xn--kltetechnik-xyz-0kb.de, otherwise Sweego api answers with 400 http code.
     */
    public function testSendForIdnDomains()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.sweego.io:8984/send', $url);
            $this->assertStringContainsString('Accept: */*', $options['headers'][2] ?? $options['request_headers'][1]);

            $body = json_decode($options['body'], true);
            // to
            $this->assertSame([
                'email' => 'kältetechnik@xn--kltetechnik-xyz-0kb.de',
                'name' => 'Kältetechnik Xyz',
            ], $body['recipients'][0]);
            // sender
            $this->assertStringContainsString('info@xn--kltetechnik-xyz-0kb.de', $body['from']['email']);
            $this->assertStringContainsString('Kältetechnik Xyz', $body['from']['name']);

            return new JsonMockResponse(['transaction_id' => 'foobar'], [
                'http_code' => 200,
            ]);
        });

        $transport = new SweegoApiTransport('ACCESS_KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('kältetechnik@kältetechnik-xyz.de', 'Kältetechnik Xyz'))
            ->from(new Address('info@kältetechnik-xyz.de', 'Kältetechnik Xyz'))
            ->text('Hello here!')
            ->html('Hello there!');

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }
}
