<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\SendPulse\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\SendPulse\Transport\SendPulseApiTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SendPulseApiTransportTest extends TestCase
{
    private Email $email;

    protected function setUp(): void
    {
        $this->email = (new Email())->from('sender@example.com')->to('to@example.com')->subject('Hello')->text('Text')->html('<p>Text</p>');
    }

    protected function tearDown(): void
    {
        unset($this->email);
    }

    public function testSendsPayloadAndReusesToken(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            return new MockResponse(str_ends_with($url, '/oauth/access_token') ? '{"access_token":"token","expires_in":3600}' : '{"result":true,"id":"message-id"}');
        });
        $transport = (new SendPulseApiTransport('client', 'secret', $client))->setHost('api.example.com')->setPort(8443);
        $this->email->cc('cc@example.com')->bcc('hidden@example.com')->replyTo('reply@example.com')->attach("\x00binary", 'file.bin');
        $envelope = new Envelope(new Address('bounce@example.com'), [new Address('to@example.com'), new Address('cc@example.com'), new Address('hidden@example.com')]);

        $sent = $transport->send($this->email, $envelope);
        $transport->send($this->email, $envelope);

        $this->assertCount(3, $requests);
        $this->assertSame('message-id', $sent->getMessageId());
        $this->assertSame('https://api.example.com:8443/oauth/access_token', $requests[0][1]);
        parse_str($requests[0][2]['body'], $auth);
        $this->assertSame(['grant_type' => 'client_credentials', 'client_id' => 'client', 'client_secret' => 'secret'], $auth);
        $this->assertSame('https://api.example.com:8443/smtp/emails', $requests[1][1]);
        $this->assertSame('POST', $requests[1][0]);
        $this->assertSame(['Authorization: Bearer token'], $requests[1][2]['normalized_headers']['authorization']);
        $this->assertSame(0, $requests[1][2]['max_redirects']);
        $payload = json_decode($requests[1][2]['body'], true)['email'];
        $this->assertSame([['email' => 'to@example.com', 'name' => '']], $payload['to']);
        $this->assertSame([['email' => 'cc@example.com', 'name' => '']], $payload['cc']);
        $this->assertSame([['email' => 'hidden@example.com', 'name' => '']], $payload['bcc']);
        $this->assertSame('bounce@example.com', $payload['from']['email']);
        $this->assertSame('reply@example.com', $payload['reply_to']['email']);
        $this->assertSame(base64_encode("\x00binary"), $payload['attachments_binary']['file.bin']);
        $this->assertSame(base64_encode('<p>Text</p>'), $payload['html']);
        $this->assertSame('Text', $payload['text']);
        $this->assertSame('Hello', $payload['subject']);
        $this->assertSame('sendpulse+api://api.example.com:8443', (string) $transport);
    }

    #[DataProvider('streamBodyProvider')]
    public function testStreamBodiesCanBeSentTwice(string $body): void
    {
        $text = fopen('php://temp', 'r+');
        $html = fopen('php://temp', 'r+');
        fwrite($text, $body);
        fwrite($html, $body);
        rewind($text);
        rewind($html);
        $payloads = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$payloads): MockResponse {
            if (str_ends_with($url, '/oauth/access_token')) {
                return new MockResponse('{"access_token":"token","expires_in":3600}');
            }

            $payloads[] = json_decode($options['body'], true)['email'];

            return new MockResponse('{"result":true,"id":"id"}');
        });
        $transport = new SendPulseApiTransport('client', 'secret', $client);
        $this->email->text($text)->html($html);

        try {
            $transport->send($this->email);
            $transport->send($this->email);

            $this->assertCount(2, $payloads);
            foreach ($payloads as $payload) {
                $this->assertSame($body, $payload['text']);
                $this->assertSame(base64_encode($body), $payload['html']);
            }
        } finally {
            fclose($text);
            fclose($html);
        }
    }

    public static function streamBodyProvider(): iterable
    {
        yield 'text' => ['Hello'];
        yield 'zero' => ['0'];
        yield 'empty' => [''];
    }

    #[DataProvider('networkFailureProvider')]
    public function testNetworkFailuresAreNotRetried(bool $duringAuthentication): void
    {
        $failedRequests = 0;
        $failure = static function () use (&$failedRequests): MockResponse {
            ++$failedRequests;

            throw new \Symfony\Component\HttpClient\Exception\TransportException('Connection lost.');
        };
        $client = new MockHttpClient($duringAuthentication ? [$failure] : [new MockResponse('{"access_token":"token","expires_in":3600}'), $failure]);
        $transport = new SendPulseApiTransport('client', 'secret', $client);

        try {
            $transport->send($this->email);
            $this->fail('A network failure must not be reported as sent.');
        } catch (TransportExceptionInterface $e) {
            $this->assertSame('Could not reach the SendPulse server.', $e->getMessage());
            $this->assertSame(1, $failedRequests);
            $this->assertSame($duringAuthentication ? 0 : 1, $client->getRequestsCount());
        }
    }

    public static function networkFailureProvider(): iterable
    {
        yield 'authentication' => [true];
        yield 'send' => [false];
    }

    public function testStreamAttachmentsAreEncodedAsAnObject(): void
    {
        $attachment = fopen('php://temp', 'r+');
        fwrite($attachment, "\x00binary");
        $this->email->attach($attachment, '0');
        $client = new MockHttpClient([
            new MockResponse('{"access_token":"token","expires_in":3600}'),
            function (string $method, string $url, array $options): MockResponse {
                $attachments = json_decode($options['body'])->email->attachments_binary;
                $this->assertInstanceOf(\stdClass::class, $attachments);
                $this->assertSame(base64_encode("\x00binary"), $attachments->{'0'});

                return new MockResponse('{"result":true,"id":"id"}');
            },
        ]);

        try {
            (new SendPulseApiTransport('client', 'secret', $client))->send($this->email);
        } finally {
            fclose($attachment);
        }
    }

    public function testEnvelopeOverridesDoNotReintroduceExcludedRecipients(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"access_token":"token","expires_in":3600}'),
            function (string $method, string $url, array $options): MockResponse {
                $payload = json_decode($options['body'], true)['email'];
                $this->assertSame([['email' => 'override@example.com', 'name' => '']], $payload['to']);
                $this->assertArrayNotHasKey('cc', $payload);
                $this->assertArrayNotHasKey('bcc', $payload);

                return new MockResponse('{"result":true,"id":"id"}');
            },
        ]);
        $this->email->cc('excluded@example.com')->bcc('hidden@example.com');
        $transport = new SendPulseApiTransport('client', 'secret', $client);

        $transport->send($this->email, new Envelope(new Address('sender@example.com'), [new Address('override@example.com')]));
    }

    public function testRefreshesTokenOnceAfterUnauthorizedResponse(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"access_token":"old","expires_in":3600}'),
            new MockResponse('', ['http_code' => 401]),
            new MockResponse('{"access_token":"new","expires_in":3600}'),
            function (string $method, string $url, array $options): MockResponse {
                $this->assertSame(['Authorization: Bearer new'], $options['normalized_headers']['authorization']);

                return new MockResponse('{"result":true,"id":"id"}');
            },
        ]);
        $transport = new SendPulseApiTransport('client', 'secret', $client);

        $transport->send($this->email);

        $this->assertSame(4, $client->getRequestsCount());
    }

    #[DataProvider('invalidResponseProvider')]
    public function testRejectsFailedResponses(array $responses, int $count): void
    {
        $client = new MockHttpClient(array_map(static fn (array $response) => new MockResponse($response[0], ['http_code' => $response[1]]), $responses));
        $transport = new SendPulseApiTransport('client', 'secret', $client);

        try {
            $transport->send($this->email);
            $this->fail('A failed response must not be reported as sent.');
        } catch (TransportExceptionInterface $e) {
            $this->assertSame($count, $client->getRequestsCount());
            $this->assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    public static function invalidResponseProvider(): iterable
    {
        $token = ['{"access_token":"token","expires_in":3600}', 200];
        yield 'oauth rejected' => [[['{}', 403]], 1];
        yield 'oauth malformed' => [[['not-json', 200]], 1];
        yield 'missing token' => [[['{"expires_in":3600}', 200]], 1];
        yield 'missing expiry' => [[['{"access_token":"token"}', 200]], 1];
        yield 'invalid expiry' => [[['{"access_token":"token","expires_in":0}', 200]], 1];
        yield 'send rejected' => [[$token, ['{}', 429]], 2];
        yield 'server failure' => [[$token, ['error', 500]], 2];
        yield 'invalid json' => [[$token, ['not-json', 200]], 2];
        yield 'negative result' => [[$token, ['{"result":false}', 200]], 2];
        yield 'missing message id' => [[$token, ['{"result":true}', 200]], 2];
        yield 'invalid message id' => [[$token, ['{"result":true,"id":[]}', 200]], 2];
        yield 'second 401' => [[$token, ['{}', 401], $token, ['{}', 401]], 4];
    }

    #[DataProvider('unsupportedMessageProvider')]
    public function testRejectsUnsupportedMessagesBeforeAuthentication(string $case): void
    {
        match ($case) {
            'inline' => $this->email->embed('image', 'logo.png', 'image/png'),
            'duplicate' => $this->email->attach('one', 'same.txt')->attach('two', 'same.txt'),
            'reply-to' => $this->email->replyTo('one@example.com', 'two@example.com'),
            'bcc-only' => $this->email->to()->bcc('hidden@example.com'),
        };
        $client = new MockHttpClient([]);
        $transport = new SendPulseApiTransport('client', 'secret', $client);

        try {
            $transport->send($this->email);
            $this->fail('The unsupported message must be rejected.');
        } catch (TransportExceptionInterface) {
            $this->assertSame(0, $client->getRequestsCount());
        }
    }

    public static function unsupportedMessageProvider(): iterable
    {
        foreach (['inline', 'duplicate', 'reply-to', 'bcc-only'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('tokenInvalidationProvider')]
    public function testRenewsExpiredOrDifferentEndpointToken(bool $changeEndpoint): void
    {
        $token = new MockResponse('{"access_token":"token","expires_in":'.($changeEndpoint ? '3600' : '1').'}');
        $client = new MockHttpClient([$token, new MockResponse('{"result":true,"id":"one"}'), new MockResponse('{"access_token":"new","expires_in":3600}'), new MockResponse('{"result":true,"id":"two"}')]);
        $transport = new SendPulseApiTransport('client', 'secret', $client);

        $transport->send($this->email);
        if ($changeEndpoint) {
            $transport->setHost('other.example.com');
        }
        $transport->send($this->email);

        $this->assertSame(4, $client->getRequestsCount());
    }

    public static function tokenInvalidationProvider(): iterable
    {
        yield 'expiring token' => [false];
        yield 'changed endpoint' => [true];
    }
}
