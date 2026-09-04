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

/**
 * Builds a {@see GoogleCloudKms} from a DSN of the form:
 *
 *     gcp-kms://default?credentials=/path/to/service-account.json
 *
 * The host is `default` (the public Cloud KMS endpoint
 * `https://cloudkms.googleapis.com/v1/`); any other host is treated as a
 * custom endpoint (private service connect, regional endpoint, ...). The
 * `credentials` query option points at a service-account JSON key file.
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

    public function supports(Dsn $dsn): bool
    {
        return self::SCHEME === $dsn->scheme;
    }

    public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, [self::SCHEME]);
        }

        self::validateOptions($dsn, ['credentials']);

        if (null === $dsn->host || '' === $dsn->host) {
            throw new InvalidArgumentException('The "gcp-kms://" DSN must include a host (use "default" for the public Cloud KMS endpoint).');
        }

        $credentials = $dsn->getRequiredOption('credentials');

        $port = null !== $dsn->port ? ':'.$dsn->port : '';
        $baseUri = 'default' === $dsn->host
            ? self::DEFAULT_BASE_URI
            : 'https://'.$dsn->host.$port.('' !== $dsn->path ? rtrim($dsn->path, '/').'/' : '/v1/');

        $client = HttpClient::createForBaseUri($baseUri);

        return new GoogleCloudKms(
            $client,
            ServiceAccountTokenProvider::fromJsonFile($client, (string) $credentials),
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
}
