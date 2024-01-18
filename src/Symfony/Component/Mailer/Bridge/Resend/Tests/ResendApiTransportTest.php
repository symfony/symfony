<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Resend\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Resend\Transport\ResendApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ResendApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(ResendApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): \Generator
    {
        yield [
            new ResendApiTransport('ACCESS_KEY'),
            'resend+api://api.resend.com',
        ];

        yield [
            (new ResendApiTransport('ACCESS_KEY'))->setHost('example.com'),
            'resend+api://example.com',
        ];

        yield [
            (new ResendApiTransport('ACCESS_KEY'))->setHost('example.com')->setPort(99),
            'resend+api://example.com:99',
        ];
    }

    public function testCustomHeader()
    {
        $params = ['param1' => 'foo', 'param2' => 'bar'];
        $json = json_encode(['custom_header_1' => 'custom_value_1']);

        $email = new Email();
        $email->getHeaders()
            ->add(new MetadataHeader('custom', $json))
            ->add(new TagHeader('TagInHeaders'))
            ->addTextHeader('templateId', 1)
            ->addParameterizedHeader('params', 'params', $params)
            ->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com', 'Alice'), [new Address('bob@system.com', 'Bob')]);

        $transport = new ResendApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(ResendApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('X-Metadata-custom', $payload['headers']);
        $this->assertEquals($json, $payload['headers']['X-Metadata-custom']);
        $this->assertArrayHasKey('tags', $payload);
        $this->assertEquals(['X-Tag' => 'TagInHeaders'], current($payload['tags']));
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
            $this->assertSame('https://api.resend.com:8984/emails', $url);
            $this->assertStringContainsString('Accept: */*', $options['headers'][2] ?? $options['request_headers'][1]);

            return new JsonMockResponse(['message' => 'i\'m a teapot'], [
                'http_code' => 418,
            ]);
        });

        $transport = new ResendApiTransport('ACCESS_KEY', $client);
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
            $this->assertSame('https://api.resend.com:8984/emails', $url);
            $this->assertStringContainsString('Accept: */*', $options['headers'][2] ?? $options['request_headers'][1]);

            return new JsonMockResponse(['id' => 'foobar'], [
                'http_code' => 200,
            ]);
        });

        $transport = new ResendApiTransport('ACCESS_KEY', $client);
        $transport->setPort(8984);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('tony.stark@marvel.com', 'Tony Stark'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello here!')
            ->html('Hello there!')
            ->addCc('foo@bar.fr')
            ->addBcc('foo@bar.fr')
            ->addReplyTo('foo@bar.fr')
            ->addPart(new DataPart('body'));

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }

    /**
     * IDN (internationalized domain names) like kältetechnik-xyz.de need to be transformed to ACE
     * (ASCII Compatible Encoding) e.g.xn--kltetechnik-xyz-0kb.de, otherwise resend api answers with 400 http code.
     */
    public function testSendForIdnDomains()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.resend.com:8984/emails', $url);
            $this->assertStringContainsString('Accept: */*', $options['headers'][2] ?? $options['request_headers'][1]);

            $body = json_decode($options['body'], true);
            // to
            $this->assertSame('kältetechnik@xn--kltetechnik-xyz-0kb.de', $body['to'][0]);
            // sender
            $this->assertStringContainsString('info@xn--kltetechnik-xyz-0kb.de', $body['from']);
            $this->assertStringContainsString('Kältetechnik Xyz', $body['from']);

            return new JsonMockResponse(['id' => 'foobar'], [
                'http_code' => 200,
            ]);
        });

        $transport = new ResendApiTransport('ACCESS_KEY', $client);
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
