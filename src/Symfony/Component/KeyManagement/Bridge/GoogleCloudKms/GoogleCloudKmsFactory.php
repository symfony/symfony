<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\GoogleCloudKms;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds a {@see GoogleCloudKms} from a DSN of the form:
 *
 *     gcp-kms://default?credentials=/path/to/service-account.json[&timeout=...&max_duration=...]
 *
 * The host is `default` (the public Cloud KMS endpoint
 * `https://cloudkms.googleapis.com/v1/`); any other host is treated as a
 * custom endpoint (private service connect, regional endpoint, ...). The
 * `credentials` query option points at a service-account JSON key file.
 *
 * When no HTTP client is given, the factory builds one with a 5-second idle timeout and a 10-second maximum duration.
 * The token requests use the same client and the same timeouts.
 * This client doesn't follow redirects. On a 307 or 308 redirect to another host, the request body would be sent again, with the signed assertion or the plaintext in it.
 * The `timeout` and `max_duration` options, in seconds, override the timeouts on either client.
 *
 * Users that need Application Default Credentials, the metadata server, or
 * any other Google-AD flow should wire {@see GoogleCloudKms} manually with
 * a custom {@see TokenProviderInterface}.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class GoogleCloudKmsFactory implements KmsFactoryInterface
{
    private const string SCHEME = 'gcp-kms';
    private const string DEFAULT_BASE_URI = 'https://cloudkms.googleapis.com/v1/';
    private const float DEFAULT_TIMEOUT = 5.0;
    private const float DEFAULT_MAX_DURATION = 10.0;

    /**
     * @param HttpClientInterface|null $client The client the DSN's base URI is applied to, so that a timeout, a retry policy or the profiler set on the application's client reach the KMS; a client of its own is built when none is given
     */
    public function __construct(
        private readonly ?HttpClientInterface $client = null,
    ) {
    }

    public function supports(#[\SensitiveParameter] Dsn $dsn): bool
    {
        return self::SCHEME === $dsn->scheme;
    }

    public function create(#[\SensitiveParameter] Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, [self::SCHEME]);
        }

        self::validateOptions($dsn, ['credentials', 'timeout', 'max_duration']);
        $timeouts = self::timeoutOptions($dsn);

        if (null === $dsn->host || '' === $dsn->host) {
            throw new InvalidArgumentException('The "gcp-kms://" DSN must include a host (use "default" for the public Cloud KMS endpoint).');
        }

        $credentials = $dsn->getRequiredOption('credentials');

        $port = null !== $dsn->port ? ':'.$dsn->port : '';
        $baseUri = 'default' === $dsn->host
            ? self::DEFAULT_BASE_URI
            : 'https://'.$dsn->host.$port.('' !== $dsn->path ? rtrim($dsn->path, '/').'/' : '/v1/');

        $client = $this->scopedClient($baseUri, $timeouts);

        return new GoogleCloudKms(
            $client,
            ServiceAccountTokenProvider::fromJsonFile($client, $credentials),
        );
    }

    /**
     * @param list<string> $supported
     */
    private static function validateOptions(Dsn $dsn, array $supported): void
    {
        foreach ($dsn->options as $option => $value) {
            if (!\in_array($option, $supported, true)) {
                throw new InvalidArgumentException(\sprintf('Unknown option "%s" in the "%s://" DSN; supported options are "%s".', $option, $dsn->scheme, implode('", "', $supported)));
            }
            if (!\is_scalar($value)) {
                throw new InvalidArgumentException(\sprintf('The "%s" option of the "%s://" DSN must be a scalar value.', $option, $dsn->scheme));
            }
        }
    }

    /**
     * @return array{timeout?: float, max_duration?: float}
     */
    private static function timeoutOptions(Dsn $dsn): array
    {
        $options = [];
        foreach (['timeout', 'max_duration'] as $option) {
            if (null === $value = $dsn->getOption($option)) {
                continue;
            }
            if (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0) {
                throw new InvalidArgumentException(\sprintf('The "%s" option of the "%s://" DSN must be positive.', $option, $dsn->scheme));
            }
            $options[$option] = (float) $value;
        }

        return $options;
    }

    /**
     * @param array{timeout?: float, max_duration?: float} $timeouts
     */
    private function scopedClient(string $baseUri, array $timeouts): HttpClientInterface
    {
        $client = $this->client ?? HttpClient::create(['timeout' => self::DEFAULT_TIMEOUT, 'max_duration' => self::DEFAULT_MAX_DURATION, 'max_redirects' => 0]);

        return $client->withOptions(['base_uri' => $baseUri] + $timeouts);
    }
}
