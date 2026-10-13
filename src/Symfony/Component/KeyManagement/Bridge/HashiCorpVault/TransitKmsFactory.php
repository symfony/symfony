<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\HashiCorpVault;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Builds a {@see TransitKms} from a DSN of the form:
 *
 *     hashicorp-vault-transit://<token>@<host>[:<port>][/<path>]?[mount=...&namespace=...&scheme=http&timeout=...&max_duration=...]
 *
 * The HTTP base URI is built from `<host>:<port><path>`; if the path is
 * empty, it defaults to `/v1/`. The token is taken from the user component
 * (the password component is ignored). The `scheme` option defaults to
 * `https`; pass `http` to talk to a local Vault dev instance.
 *
 * The HTTP client given to the factory is what the DSN's base URI is applied to, so that its timeout, retry policy or certificates reach Vault.
 * Without one, the factory builds a client with a 5-second idle timeout and a 10-second maximum duration.
 * This client doesn't follow redirects, since the `X-Vault-Token` header would follow a redirect to another host.
 * The `timeout` and `max_duration` options, in seconds, override the timeouts on either client.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class TransitKmsFactory implements KmsFactoryInterface
{
    private const string SCHEME = 'hashicorp-vault-transit';
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

        self::validateOptions($dsn, ['mount', 'namespace', 'scheme', 'timeout', 'max_duration']);
        $timeouts = self::timeoutOptions($dsn);

        if (null === $dsn->host || '' === $dsn->host) {
            throw new InvalidArgumentException('The "hashicorp-vault-transit://" DSN must include the Vault host (e.g. "hashicorp-vault-transit://<token>@vault.example.com:8200/v1/").');
        }
        if (null === $dsn->user || '' === $dsn->user) {
            throw new InvalidArgumentException('The "hashicorp-vault-transit://" DSN must include the Vault token in the user component.');
        }

        $scheme = $dsn->getOption('scheme', 'https');
        if ('http' !== $scheme && 'https' !== $scheme) {
            throw new InvalidArgumentException(\sprintf('The "hashicorp-vault-transit://" DSN "scheme" option must be either "http" or "https", "%s" given.', $scheme));
        }

        $port = null !== $dsn->port ? ':'.$dsn->port : '';
        $path = '' !== $dsn->path ? rtrim($dsn->path, '/').'/' : '/v1/';
        $baseUri = $scheme.'://'.$dsn->host.$port.$path;

        return new TransitKms(
            $this->scopedClient($baseUri, $timeouts),
            $dsn->user,
            $dsn->getOption('mount', 'transit'),
            $dsn->getOption('namespace'),
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
