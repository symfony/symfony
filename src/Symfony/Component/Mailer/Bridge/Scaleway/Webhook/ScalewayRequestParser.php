<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\Webhook;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\MethodRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Mailer\Bridge\Scaleway\RemoteEvent\ScalewayPayloadConverter;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\RemoteEvent\Event\Mailer\AbstractMailerEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Parses SNS-style webhook notifications sent by Scaleway Topics and Events.
 *
 * Messages are signed with a certificate referenced by their "SigningCertURL"
 * field. That certificate is only trusted after being validated against the
 * Scaleway CA trust chain bundled with this package.
 *
 * @see https://www.scaleway.com/en/docs/topics-and-events/reference-content/verifying-webhooks/
 */
final class ScalewayRequestParser extends AbstractRequestParser
{
    private const SIGNING_CERT_URL = '{^https://messaging\.s3\.[a-z]{2}-[a-z]{3}\.scw\.cloud/}i';
    private const TIMESTAMP_FORMAT = 'Y-m-d\TH:i:s.v\Z';
    // retried deliveries keep the timestamp of the original publication
    private const TIMESTAMP_TOLERANCE = 8 * 3600;

    private const SIGNED_KEYS = [
        'Notification' => ['Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type'],
        'SubscriptionConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
        'UnsubscribeConfirmation' => ['Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type'],
    ];

    /**
     * @param CacheInterface|null $cache      Caches the signing certificates to avoid fetching them on every request
     * @param string              $trustChain Path to a PEM file containing the CA certificates that must have issued the signing certificate
     */
    public function __construct(
        private readonly ScalewayPayloadConverter $converter,
        private ?HttpClientInterface $httpClient = null,
        private readonly ?CacheInterface $cache = null,
        private readonly string $trustChain = __DIR__.'/../Resources/sns-trust-chain.pem',
    ) {
    }

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new MethodRequestMatcher('POST');
    }

    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): ?AbstractMailerEvent
    {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        foreach (['Type', 'MessageId', 'TopicArn', 'Timestamp', 'Signature', 'SignatureVersion', 'SigningCertURL'] as $key) {
            if (!\is_string($payload[$key] ?? null)) {
                throw new RejectWebhookException(406, 'Payload is malformed.');
            }
        }

        if (!$timestamp = \DateTimeImmutable::createFromFormat(self::TIMESTAMP_FORMAT, $payload['Timestamp'], new \DateTimeZone('UTC'))) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        if (abs(time() - $timestamp->getTimestamp()) > self::TIMESTAMP_TOLERANCE) {
            throw new RejectWebhookException(406, 'Timestamp is outside the allowed time window.');
        }

        $this->verifySignature($payload);

        if ('SubscriptionConfirmation' === $payload['Type']) {
            $this->confirmSubscription($payload['SubscribeURL'] ?? '');

            return null;
        }

        if ('UnsubscribeConfirmation' === $payload['Type']) {
            return null;
        }

        try {
            $message = json_decode($payload['Message'] ?? '', true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        if (!\is_array($message) || !isset($message['type'])) {
            throw new RejectWebhookException(406, 'Payload is malformed.');
        }

        try {
            return $this->converter->convert($message);
        } catch (ParseException $e) {
            throw new RejectWebhookException(406, $e->getMessage(), $e);
        }
    }

    private function verifySignature(array $payload): void
    {
        if (!$signedKeys = self::SIGNED_KEYS[$payload['Type']] ?? null) {
            throw new RejectWebhookException(406, \sprintf('Unsupported message type "%s".', $payload['Type']));
        }

        $algo = match ($payload['SignatureVersion']) {
            '1' => \OPENSSL_ALGO_SHA1,
            '2' => \OPENSSL_ALGO_SHA256,
            default => throw new RejectWebhookException(406, \sprintf('Unsupported signature version "%s".', $payload['SignatureVersion'])),
        };

        if (false === $signature = base64_decode($payload['Signature'], true)) {
            throw new RejectWebhookException(406, 'Signature is invalid.');
        }

        $signedString = '';
        foreach ($signedKeys as $key) {
            if (!isset($payload[$key])) {
                continue;
            }

            if (!\is_string($payload[$key])) {
                throw new RejectWebhookException(406, 'Payload is malformed.');
            }

            $signedString .= $key."\n".$payload[$key]."\n";
        }

        $certUrl = $payload['SigningCertURL'];
        if (!preg_match(self::SIGNING_CERT_URL, $certUrl)) {
            throw new RejectWebhookException(406, 'The signing certificate URL must point to Scaleway over HTTPS.');
        }

        $cacheKey = 'scaleway_sns_cert.'.hash('xxh128', $certUrl);
        $fromCache = null !== $this->cache;
        $fetch = function () use ($certUrl, &$fromCache): string {
            $fromCache = false;

            return $this->fetchCertificate($certUrl);
        };

        $certificate = $this->cache ? $this->cache->get($cacheKey, $fetch) : $fetch();

        if ($this->verify($certificate, $signedString, $signature, $algo)) {
            return;
        }

        if ($fromCache) {
            // the certificate might have been rotated since it was cached; retry with a fresh one
            $this->cache->delete($cacheKey);
            $certificate = $this->cache->get($cacheKey, $fetch);

            if ($this->verify($certificate, $signedString, $signature, $algo)) {
                return;
            }
        }

        throw new RejectWebhookException(406, 'Signature is invalid.');
    }

    private function verify(string $certificate, string $signedString, string $signature, int $algo): bool
    {
        if (!$cert = @openssl_x509_read($certificate)) {
            return false;
        }

        if (true !== openssl_x509_checkpurpose($cert, \X509_PURPOSE_ANY, [$this->trustChain])) {
            return false;
        }

        if (!$publicKey = openssl_pkey_get_public($cert)) {
            return false;
        }

        return 1 === openssl_verify($signedString, $signature, $publicKey, $algo);
    }

    private function fetchCertificate(string $url): string
    {
        try {
            return $this->getHttpClient()->request('GET', $url)->getContent();
        } catch (ExceptionInterface $e) {
            throw new RejectWebhookException(406, 'Unable to fetch the signing certificate.', $e);
        }
    }

    private function confirmSubscription(string $url): void
    {
        if ('https' !== strtolower((string) parse_url($url, \PHP_URL_SCHEME))) {
            throw new RejectWebhookException(406, 'The subscription confirmation URL must use HTTPS.');
        }

        try {
            $this->getHttpClient()->request('GET', $url)->getContent();
        } catch (ExceptionInterface $e) {
            throw new RejectWebhookException(406, 'Unable to confirm the subscription.', $e);
        }
    }

    private function getHttpClient(): HttpClientInterface
    {
        return $this->httpClient ??= class_exists(HttpClient::class) ? HttpClient::create() : throw new LogicException(\sprintf('You cannot use "%s" as the HttpClient component is not installed. Try running "composer require symfony/http-client".', self::class));
    }
}
