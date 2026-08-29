<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\Tests\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ClockMock;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Bridge\Scaleway\RemoteEvent\ScalewayPayloadConverter;
use Symfony\Component\Mailer\Bridge\Scaleway\Webhook\ScalewayRequestParser;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Group('time-sensitive')]
class ScalewayRequestParserSignatureTest extends TestCase
{
    public static function getMalformedTimestamps(): iterable
    {
        yield 'no milliseconds' => ['2026-01-15T10:30:01Z'];
        yield 'no timezone' => ['2026-01-15T10:30:01.000'];
        yield 'unix time' => ['1768473001'];
        yield 'relative' => ['now'];
    }

    #[DataProvider('getMalformedTimestamps')]
    public function testRejectsMalformedTimestamp(string $timestamp)
    {
        $envelope = $this->createSignedEnvelope(timestamp: $timestamp);
        $parser = $this->createParser($this->createCertClient());

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload is malformed.');
        $parser->parse($this->createRequest($envelope), '');
    }

    public function testRejectsTamperedPayload()
    {
        $envelope = $this->createSignedEnvelope();
        $envelope['Message'] = json_encode(['type' => 'email_delivered', 'id' => 'tampered', 'email_id' => 'tampered', 'created_at' => '2026-01-15T10:30:00Z', 'email_to' => 'attacker@example.com']);
        $parser = $this->createParser($this->createCertClient());

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is invalid.');
        $parser->parse($this->createRequest($envelope), '');
    }

    #[DataProvider('provideNonStringSignedFields')]
    public function testRejectsNonStringSignedField(string $type, string $field)
    {
        $envelope = $this->createSignedEnvelope(type: $type);
        $envelope[$field] = ['not a string'];
        $parser = $this->createParser($this->createCertClient());

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Payload is malformed.');
        $parser->parse($this->createRequest($envelope), '');
    }

    public static function provideNonStringSignedFields(): iterable
    {
        yield ['Notification', 'Message'];
        yield ['Notification', 'Subject'];
        yield ['SubscriptionConfirmation', 'SubscribeURL'];
        yield ['SubscriptionConfirmation', 'Token'];
    }

    public function testRejectsCertificateNotIssuedByTrustedCA()
    {
        // the signature itself is valid, but the certificate is not issued by the bundled CA
        $envelope = $this->createSignedEnvelope(key: __DIR__.'/Fixtures/rogue.key');
        $parser = $this->createParser(new MockHttpClient(static fn () => new MockResponse(file_get_contents(__DIR__.'/Fixtures/rogue.crt'))));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Signature is invalid.');
        $parser->parse($this->createRequest($envelope), '');
    }

    #[DataProvider('provideForeignSigningCertUrls')]
    public function testRejectsSigningCertUrlOnForeignHostWithoutFetchingIt(string $certUrl)
    {
        $envelope = $this->createSignedEnvelope(certUrl: $certUrl);
        $parser = $this->createParser(new MockHttpClient(static fn () => throw new \LogicException('The certificate must not be fetched.')));

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('The signing certificate URL must point to Scaleway over HTTPS.');
        $parser->parse($this->createRequest($envelope), '');
    }

    public static function provideForeignSigningCertUrls(): iterable
    {
        yield 'plain http' => ['http://messaging.s3.fr-par.scw.cloud/certs/cert-11111111.pem'];
        yield 'other domain' => ['https://example.com/cert.pem'];
        yield 'other bucket on Scaleway object storage' => ['https://attacker.s3.fr-par.scw.cloud/cert.pem'];
        yield 'lookalike domain' => ['https://messaging.s3.fr-par.scw.cloud.example.com/cert.pem'];
        yield 'userinfo trick' => ['https://messaging.s3.fr-par.scw.cloud@example.com/cert.pem'];
        yield 'private address' => ['https://169.254.169.254/latest/meta-data/'];
        yield 'explicit port' => ['https://messaging.s3.fr-par.scw.cloud:8443/cert.pem'];
        yield 'backslash after host' => ['https://messaging.s3.fr-par.scw.cloud\\@example.com/cert.pem'];
        yield 'host without path' => ['https://messaging.s3.fr-par.scw.cloud'];
        yield 'leading whitespace' => [' https://messaging.s3.fr-par.scw.cloud/cert.pem'];
    }

    public function testAcceptsSigningCertUrlOnEveryScalewayRegion()
    {
        $envelope = $this->createSignedEnvelope(certUrl: 'https://messaging.s3.nl-ams.scw.cloud/nl-ams/sns/sns_certificate_123.crt');
        $parser = $this->createParser($this->createCertClient());

        $this->assertInstanceOf(MailerDeliveryEvent::class, $parser->parse($this->createRequest($envelope), ''));
    }

    public function testRejectsUnsupportedSignatureVersion()
    {
        $envelope = $this->createSignedEnvelope();
        $envelope['SignatureVersion'] = '3';
        $parser = $this->createParser($this->createCertClient());

        $this->expectException(RejectWebhookException::class);
        $this->expectExceptionMessage('Unsupported signature version "3".');
        $parser->parse($this->createRequest($envelope), '');
    }

    public function testAcceptsSha1Signature()
    {
        $envelope = $this->createSignedEnvelope(signatureVersion: '1');
        $parser = $this->createParser($this->createCertClient());

        $event = $parser->parse($this->createRequest($envelope), '');

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame(MailerDeliveryEvent::DELIVERED, $event->getName());
    }

    public function testConfirmsSubscription()
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests) {
            $requests[] = [$method, $url];

            return new MockResponse(str_ends_with($url, '.pem') ? file_get_contents(__DIR__.'/Fixtures/signing.crt') : 'OK');
        });
        $parser = $this->createParser($client);
        $envelope = $this->createSignedEnvelope(type: 'SubscriptionConfirmation');

        $this->assertNull($parser->parse($this->createRequest($envelope), ''));
        $this->assertCount(2, $requests);
        $this->assertSame(['GET', 'https://messaging.s3.fr-par.scw.cloud/subscribe?token=abc'], $requests[1]);
    }

    public function testIgnoresUnsubscribeConfirmation()
    {
        $envelope = $this->createSignedEnvelope(type: 'UnsubscribeConfirmation');
        $parser = $this->createParser($this->createCertClient());

        $this->assertNull($parser->parse($this->createRequest($envelope), ''));
    }

    public function testCertificateIsCached()
    {
        $fetchCount = 0;
        $client = new MockHttpClient(static function () use (&$fetchCount) {
            ++$fetchCount;

            return new MockResponse(file_get_contents(__DIR__.'/Fixtures/signing.crt'));
        });
        $parser = $this->createParser($client, new ArrayAdapter());

        $this->assertInstanceOf(MailerDeliveryEvent::class, $parser->parse($this->createRequest($this->createSignedEnvelope()), ''));
        $this->assertInstanceOf(MailerDeliveryEvent::class, $parser->parse($this->createRequest($this->createSignedEnvelope()), ''));
        $this->assertSame(1, $fetchCount);
    }

    public function testRotatedCertificateIsRefetched()
    {
        $fetchCount = 0;
        $servedCert = __DIR__.'/Fixtures/signing2.crt';
        $client = new MockHttpClient(static function () use (&$fetchCount, &$servedCert) {
            ++$fetchCount;

            return new MockResponse(file_get_contents($servedCert));
        });
        $cache = new ArrayAdapter();
        $parser = $this->createParser($client, $cache);

        // prime the cache with the old certificate
        $parser->parse($this->createRequest($this->createSignedEnvelope(key: __DIR__.'/Fixtures/signing2.key')), '');

        // the certificate has been rotated: the cached one no longer matches the signature
        $servedCert = __DIR__.'/Fixtures/signing.crt';
        $event = $parser->parse($this->createRequest($this->createSignedEnvelope()), '');

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame(2, $fetchCount);
    }

    private function createParser(HttpClientInterface $client, ?CacheInterface $cache = null): ScalewayRequestParser
    {
        return new ScalewayRequestParser(new ScalewayPayloadConverter(), $client, $cache, __DIR__.'/Fixtures/trust-chain.pem');
    }

    private function createCertClient(): MockHttpClient
    {
        return new MockHttpClient(static fn () => new MockResponse(file_get_contents(__DIR__.'/Fixtures/signing.crt')));
    }

    private function createSignedEnvelope(string $type = 'Notification', string $key = __DIR__.'/Fixtures/signing.key', string $certUrl = 'https://messaging.s3.fr-par.scw.cloud/certs/cert-11111111.pem', string $signatureVersion = '2', string $timestamp = '2026-01-15T10:30:01.000Z'): array
    {
        $envelope = [
            'Type' => $type,
            'MessageId' => '9ae5c56c-6c9c-42e5-b0b1-0fe0f8bbdbf7',
            'TopicArn' => 'arn:scw:sns:fr-par:project-8c8bfa06:mailer-events',
            'Message' => json_encode(['type' => 'email_delivered', 'id' => 'af5c1aac-cf1b-4d4d-9e46-e6d0cd40b81c', 'email_id' => 'd4fbec9d-eed9-44d5-af47-c1126467a5ca', 'created_at' => '2026-01-15T10:30:00Z', 'email_to' => 'recipient@example.com']),
            'Timestamp' => $timestamp,
            'SignatureVersion' => $signatureVersion,
            'SigningCertURL' => $certUrl,
        ];

        if ('Notification' !== $type) {
            $envelope['Message'] = 'You have chosen to subscribe to a topic.';
            $envelope['Token'] = 'abc';
            $envelope['SubscribeURL'] = 'https://messaging.s3.fr-par.scw.cloud/subscribe?token=abc';
        }

        $signedKeys = 'Notification' === $type
            ? ['Message', 'MessageId', 'Timestamp', 'TopicArn', 'Type']
            : ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'];

        $signedString = '';
        foreach ($signedKeys as $signedKey) {
            $signedString .= $signedKey."\n".$envelope[$signedKey]."\n";
        }
        openssl_sign($signedString, $signature, file_get_contents($key), '1' === $signatureVersion ? \OPENSSL_ALGO_SHA1 : \OPENSSL_ALGO_SHA256);
        $envelope['Signature'] = base64_encode($signature);

        return $envelope;
    }

    private function createRequest(array $envelope): Request
    {
        ClockMock::withClockMock(1768473001);

        return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'text/plain; charset=UTF-8'], json_encode($envelope));
    }
}
