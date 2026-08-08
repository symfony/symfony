<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\AwsKms;

use AsyncAws\Core\Configuration;
use AsyncAws\Kms\KmsClient;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;

/**
 * Builds an {@see AwsKms} from a DSN of the form:
 *
 *     aws-kms://[<accessKey>:<secretKey>@]<host>[:<port>]?region=eu-west-1[&session_token=...&scheme=http]
 *
 * The host `default` selects the public AWS endpoint for the given region;
 * any other host is treated as a custom endpoint (LocalStack, VPC endpoint,
 * ...). Leaving the credentials out lets async-aws fall back to the standard
 * provider chain (env vars, ~/.aws/credentials, instance profile, ...). The
 * `scheme` option defaults to `https`; pass `http` to talk to a local
 * sandbox (LocalStack).
 *
 * Users that need fine-grained tuning (custom HTTP client, retry, ...)
 * should wire {@see AwsKms} manually.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class AwsKmsFactory implements KmsFactoryInterface
{
    private const string SCHEME = 'aws-kms';

    public function supports(Dsn $dsn): bool
    {
        return self::SCHEME === $dsn->scheme;
    }

    public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, [self::SCHEME]);
        }

        self::validateOptions($dsn, ['region', 'session_token', 'scheme']);

        if (null === $dsn->host || '' === $dsn->host) {
            throw new InvalidArgumentException('The "aws-kms://" DSN must include a host (use "default" for the public AWS endpoint).');
        }

        if ((null === $dsn->user) !== (null === $dsn->password)) {
            throw new InvalidArgumentException(null === $dsn->user ? 'The "aws-kms://" DSN defines a secret key without an access key id; provide both or neither.' : 'The "aws-kms://" DSN defines an access key id without a secret key; provide both or neither.');
        }

        $region = $dsn->getOption('region');
        if (null === $region || '' === $region) {
            throw new InvalidArgumentException('The "aws-kms://" DSN must include a "region" option (e.g. "?region=eu-west-1").');
        }

        $options = ['region' => (string) $region];
        if (null !== $dsn->user) {
            $options['accessKeyId'] = $dsn->user;
        }
        if (null !== $dsn->password) {
            $options['accessKeySecret'] = $dsn->password;
        }
        if (null !== $sessionToken = $dsn->getOption('session_token')) {
            $options['sessionToken'] = (string) $sessionToken;
        }
        $scheme = $dsn->getOption('scheme');
        if ('default' === $dsn->host) {
            if (null !== $scheme) {
                throw new InvalidArgumentException('The "aws-kms://" DSN "scheme" option is only meaningful with a custom host; the "default" host always uses the public AWS endpoint over HTTPS.');
            }
        } else {
            $scheme = (string) ($scheme ?? 'https');
            if ('http' !== $scheme && 'https' !== $scheme) {
                throw new InvalidArgumentException(\sprintf('The "aws-kms://" DSN "scheme" option must be either "http" or "https", "%s" given.', $scheme));
            }
            $options['endpoint'] = $scheme.'://'.$dsn->host.(null !== $dsn->port ? ':'.$dsn->port : '');
        }

        return new AwsKms(new KmsClient(Configuration::create($options)));
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
