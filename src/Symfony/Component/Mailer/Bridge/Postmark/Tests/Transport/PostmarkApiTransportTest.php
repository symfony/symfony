<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Postmark\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\MessageStreamHeader;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PostmarkApiTransportTest extends TestCase
{
    /**
     * @dataProvider getTransportData
     */
    public function testToString(PostmarkApiTransport $transport, string $expected)
    {
        $this->assertSame($expected, (string) $transport);
    }

    public static function getTransportData()
    {
        return [
            [
                new PostmarkApiTransport('KEY'),
                'postmark+api://api.postmarkapp.com',
            ],
            [
                (new PostmarkApiTransport('KEY'))->setHost('example.com'),
                'postmark+api://example.com',
            ],
            [
                (new PostmarkApiTransport('KEY'))->setHost('example.com')->setPort(99),
                'postmark+api://example.com:99',
            ],
        ];
    }

    public function testCustomHeader()
    {
        $email = new Email();
        $email->getHeaders()->addTextHeader('foo', 'bar');
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new PostmarkApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayHasKey('Headers', $payload);
        $this->assertCount(1, $payload['Headers']);

        $this->assertEquals(['Name' => 'foo', 'Value' => 'bar'], $payload['Headers'][0]);
    }

    public function testSend()
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->assertSame('POST', $method);
            $this->assertSame('https://api.postmarkapp.com/email', $url);
            $this->assertStringContainsStringIgnoringCase('X-Postmark-Server-Token: KEY', $options['headers'][1] ?? $options['request_headers'][1]);

            $body = json_decode($options['body'], true);
            $this->assertSame('"Fabien" <fabpot@symfony.com>', $body['From']);
            $this->assertSame('"Saif Eddin" <saif.gmati@symfony.com>', $body['To']);
            $this->assertSame('Hello!', $body['Subject']);
            $this->assertSame('Hello There!', $body['TextBody']);

            return new MockResponse(json_encode(['MessageID' => 'foobar']), [
                'http_code' => 200,
            ]);
        });

        $transport = new PostmarkApiTransport('KEY', $client);

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
        $client = new MockHttpClient(static fn (string $method, string $url, array $options): ResponseInterface => new MockResponse(json_encode(['Message' => 'i\'m a teapot', 'ErrorCode' => 418]), [
            'http_code' => 418,
            'response_headers' => [
                'content-type' => 'application/json',
            ],
        ]));
        $transport = new PostmarkApiTransport('KEY', $client);
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

    public function testTagAndMetadataAndMessageStreamHeaders()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('password-reset'));
        $email->getHeaders()->add(new MetadataHeader('Color', 'blue'));
        $email->getHeaders()->add(new MetadataHeader('Client-ID', '12345'));
        $email->getHeaders()->add(new MessageStreamHeader('broadcasts'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new PostmarkApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkApiTransport::class, 'getPayload');
        $payload = $method->invoke($transport, $email, $envelope);

        $this->assertArrayNotHasKey('Headers', $payload);
        $this->assertArrayHasKey('Tag', $payload);
        $this->assertArrayHasKey('Metadata', $payload);
        $this->assertArrayHasKey('MessageStream', $payload);

        $this->assertSame('password-reset', $payload['Tag']);
        $this->assertSame(['Color' => 'blue', 'Client-ID' => '12345'], $payload['Metadata']);
        $this->assertSame('broadcasts', $payload['MessageStream']);
    }

    public function testMultipleTagsAreNotAllowed()
    {
        $email = new Email();
        $email->getHeaders()->add(new TagHeader('tag1'));
        $email->getHeaders()->add(new TagHeader('tag2'));
        $envelope = new Envelope(new Address('alice@system.com'), [new Address('bob@system.com')]);

        $transport = new PostmarkApiTransport('ACCESS_KEY');
        $method = new \ReflectionMethod(PostmarkApiTransport::class, 'getPayload');

        $this->expectException(TransportException::class);

        $method->invoke($transport, $email, $envelope);
    }
}
