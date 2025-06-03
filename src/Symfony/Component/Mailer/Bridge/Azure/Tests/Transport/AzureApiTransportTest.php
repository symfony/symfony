<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Azure\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Bridge\Azure\Transport\AzureApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AzureApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(AzureApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData(): array
    {
        return [
            [
                new AzureApiTransport('KEY', 'ACS_RESOURCE_NAME'),
                'azure+api://ACS_RESOURCE_NAME.communication.azure.com',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AzureApiTransport('KEY', 'ACS_RESOURCE_NAME');
        $method = new \ReflectionMethod(AzureApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('headers', $payload);
        $this->assertArrayHasKey('foo', $payload['headers']);
        $this->assertEquals('bar', $payload['headers']['foo']);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://my-acs-resource.communication.azure.com/emails:send?api-version=2023-03-31', $url);

            $body = json_decode($options['body'], true);

            $message = $body['content'];
            $this->assertSame('normal', $body['importance']);
            $this->assertSame('fabpot@symfony.com', $body['senderAddress']);
            $this->assertSame('Saif Eddin', $body['recipients']['to'][0]['displayName']);
            $this->assertSame('saif.gmati@symfony.com', $body['recipients']['to'][0]['address']);
            $this->assertSame('Hello!', $message['subject']);
            $this->assertSame('Hello There!', $message['plainText']);

            $this->assertSame([
                [
                    'name' => 'Hello There!',
                    'contentInBase64' => base64_encode('content'),
                    'contentType' => 'text/plain',
                ],
            ], $body['attachments']);

            return new JsonMockResponse([
                'id' => 'foobar',
            ], [
                'http_code' => 202,
            ]);
        });

        $transport = new AzureApiTransport('KEY', 'my-acs-resource', true, '2023-03-31', $client);

        $mail = new Email();
        $mail->subject('Hello!')
            ->to(new Address('saif.gmati@symfony.com', 'Saif Eddin'))
            ->from(new Address('fabpot@symfony.com', 'Fabien'))
            ->text('Hello There!')
            ->attach('content', 'Hello There!', 'text/plain');

        $message = $transport->send($mail);

        $this->assertSame('foobar', $message->getMessageId());
    }

    public function testTagAndMetadataHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('category-one'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new AzureApiTransport('KEY', 'ACS_RESOURCE_NAME');
        $method = new \ReflectionMethod(AzureApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('headers', $payload);
        $this->assertArrayHasKey('X-Tag', $payload['headers']);
        $this->assertArrayHasKey('X-Metadata-Color', $payload['headers']);
        $this->assertArrayHasKey('X-Metadata-Client-ID', $payload['headers']);

        $this->assertCount(3, $payload['headers']);

        $this->assertSame('category-one', $payload['headers']['X-Tag']);
        $this->assertSame('blue', $payload['headers']['X-Metadata-Color']);
        $this->assertSame('12345', $payload['headers']['X-Metadata-Client-ID']);
    }

    public function testItDoesNotAllowToAddResourceNameWithDot()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Resource name must not end with a dot "."');

        new AzureApiTransport('KEY', 'ACS_RESOURCE_NAME.');
    }
}
