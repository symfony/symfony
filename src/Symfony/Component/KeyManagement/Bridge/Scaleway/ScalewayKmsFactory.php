<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Scaleway;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds a {@see ScalewayKms} from a DSN of the form:
 *
 *     scaleway-kms://<secretKey>@<host>[:<port>]?region=fr-par[&timeout=...&max_duration=...]
 *
 * The host `default` selects the public Scaleway API; any other host is used as a custom HTTPS endpoint.
 *
 * The HTTP client given to the factory is what the regional base URI is applied to, so that its timeout, retry policy or certificates reach Scaleway.
 * Without one, the factory builds a client with a 5-second idle timeout and a 10-second maximum duration.
 * The `timeout` and `max_duration` options, in seconds, override the timeouts on either client.
 * Requests don't follow redirects, whatever the client, since the `X-Auth-Token` header would follow a redirect to another host.
 *
 * @author Marcus Stöhr <marcus@fischteich.net>
 *
 * @experimental
 */
final class ScalewayKmsFactory implements KmsFactoryInterface
{
    private const string SCHEME = 'scaleway-kms';
    private const string DEFAULT_ENDPOINT = 'https://api.scaleway.com';
    private const string REGION_PATTERN = '/^[a-z]{2}-[a-z]{3}$/D';
    private const float DEFAULT_TIMEOUT = 5.0;
    private const float DEFAULT_MAX_DURATION = 10.0;

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

        self::validateOptions($dsn, ['region', 'timeout', 'max_duration']);
        $timeouts = self::timeoutOptions($dsn);

        if (null === $dsn->host || '' === $dsn->host) {
            throw new InvalidArgumentException('The "scaleway-kms://" DSN must include a host (use "default" for the public Scaleway API).');
        }
        if (null === $dsn->user || '' === $dsn->user) {
            throw new InvalidArgumentException('The "scaleway-kms://" DSN must include the Scaleway secret key in the user component.');
        }
        if (null !== $dsn->password) {
            throw new InvalidArgumentException('The "scaleway-kms://" DSN must not include a password: put the Scaleway secret key in the user component (e.g. "scaleway-kms://<secret_key>@default?region=fr-par"); the project id is not needed.');
        }

        $region = $dsn->getOption('region');
        if (null === $region || !preg_match(self::REGION_PATTERN, $region)) {
            throw new InvalidArgumentException('The "scaleway-kms://" DSN must include a valid "region" option (e.g. "?region=fr-par").');
        }

        $endpoint = 'default' === $dsn->host ? self::DEFAULT_ENDPOINT : 'https://'.$dsn->host.(null !== $dsn->port ? ':'.$dsn->port : '');
        $baseUri = \sprintf('%s/key-manager/v1alpha1/regions/%s/', $endpoint, $region);

        return new ScalewayKms($this->scopedClient($baseUri, $timeouts), $dsn->user);
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
