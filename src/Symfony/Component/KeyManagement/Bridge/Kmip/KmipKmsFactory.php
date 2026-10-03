<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip;

use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcmEncryptionScheme;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;

/**
 * Builds a {@see KmipKms} from a DSN of the form:
 *
 *     kmip://[<user>:<password>@]<host>[:<port>]?cert=<path>&key=<path>&version=<version>[&ca=<path>][&peer_name=<name>][&passphrase=<passphrase>][&cipher=aes-gcm][&iv_length=16][&timeout=10]
 *
 * A client certificate and private key are required even when a username and password are supplied.
 * The `ca` option names a certificate authority file used to verify the server certificate; omitting it uses the system trust store.
 * The `peer_name` option controls the expected server name. The DSN has no path.
 *
 * The KMIP version must be selected explicitly; AES-GCM is the default cipher. The `cipher` option names a scheme in the registry supplied to the factory, which can include application-defined schemes. The `iv_length` option selects the AES-GCM IV length for new ciphertext without changing the stored scheme name.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @experimental
 */
final class KmipKmsFactory implements KmsFactoryInterface
{
    /**
     * Default TCP port assigned to KMIP.
     */
    private const int DEFAULT_PORT = 5696;
    /**
     * Default connection timeout in seconds, represented as a DSN option string.
     */
    private const string DEFAULT_TIMEOUT = '10';
    private const string DSN_SCHEME = 'kmip';
    private const string DEFAULT_CIPHER = 'aes-gcm';
    /**
     * @var list<string>
     */
    private const array SUPPORTED_OPTIONS = ['cert', 'key', 'version', 'cipher', 'iv_length', 'ca', 'peer_name', 'passphrase', 'timeout'];
    /**
     * @var list<string>
     */
    private const array STRING_OPTIONS = ['version', 'cipher', 'iv_length', 'ca', 'peer_name', 'passphrase', 'timeout'];

    public function __construct(private readonly KmipEncryptionSchemeRegistry $schemes)
    {
    }

    public function supports(#[\SensitiveParameter] Dsn $dsn): bool
    {
        return self::DSN_SCHEME === $dsn->scheme;
    }

    public function create(#[\SensitiveParameter] Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, [self::DSN_SCHEME]);
        }
        foreach ($dsn->options as $option => $value) {
            if (!\in_array($option, self::SUPPORTED_OPTIONS, true)) {
                throw new InvalidArgumentException(\sprintf('Unknown option "%s" in the "kmip://" DSN.', $option));
            }
            if (!\is_scalar($value)) {
                throw new InvalidArgumentException(\sprintf('The "%s" option of the "kmip://" DSN must be scalar.', $option));
            }
        }
        if (null === $dsn->host || '' === $dsn->host || '' !== $dsn->path) {
            throw new InvalidArgumentException('The "kmip://" DSN requires a host and no path.');
        }
        $host = $dsn->host;
        if (str_starts_with($host, '[')) {
            if (!str_ends_with($host, ']') || false === filter_var(substr($host, 1, -1), \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6)) {
                throw new InvalidArgumentException('The KMIP host is invalid.');
            }
            $host = substr($host, 1, -1);
        }
        $clientCertificateFile = $dsn->getOption('cert');
        $clientPrivateKeyFile = $dsn->getOption('key');
        if (!\is_string($clientCertificateFile) || '' === $clientCertificateFile || !\is_string($clientPrivateKeyFile) || '' === $clientPrivateKeyFile) {
            throw new InvalidArgumentException('The "kmip://" DSN requires nonempty "cert" and "key" options.');
        }
        if (!\array_key_exists('version', $dsn->options)) {
            throw new InvalidArgumentException('The "kmip://" DSN requires a "version" option.');
        }
        foreach (self::STRING_OPTIONS as $option) {
            if (\array_key_exists($option, $dsn->options) && !\is_string($dsn->options[$option])) {
                throw new InvalidArgumentException(\sprintf('The "%s" option of the "kmip://" DSN must be a string.', $option));
            }
        }
        $timeout = $dsn->getOption('timeout', self::DEFAULT_TIMEOUT);
        if (!is_numeric($timeout) || !is_finite((float) $timeout) || (float) $timeout <= 0) {
            throw new InvalidArgumentException('The "timeout" option of the "kmip://" DSN must be positive.');
        }
        if (null !== $dsn->user && null === $dsn->password || null === $dsn->user && null !== $dsn->password) {
            throw new InvalidArgumentException('The "kmip://" DSN requires both username and password when either is set.');
        }
        $versionName = $dsn->getOption('version');
        $version = \is_string($versionName) ? KmipVersion::tryFrom($versionName) : null;
        if (null === $version) {
            $versions = array_map(static fn (KmipVersion $version): string => $version->value, KmipVersion::cases());
            $lastVersion = array_pop($versions);
            $versionList = $versions ? implode(', ', $versions).' and '.$lastVersion : $lastVersion;

            throw new InvalidArgumentException('Supported KMIP versions are '.$versionList.'.');
        }
        $cipher = $dsn->getOption('cipher', self::DEFAULT_CIPHER);
        // Validate the DSN cipher before constructing the client; KmipKms resolves it for use.
        $this->schemes->get($cipher);
        $schemes = $this->schemes;
        if (\array_key_exists('iv_length', $dsn->options)) {
            if (self::DEFAULT_CIPHER !== $cipher || !$schemes->get($cipher) instanceof AesGcmEncryptionScheme) {
                throw new InvalidArgumentException('The "iv_length" option is only supported with "aes-gcm".');
            }
            $ivLength = $dsn->getOption('iv_length');
            if (!ctype_digit($ivLength)) {
                throw new InvalidArgumentException('The "iv_length" option of the "kmip://" DSN must be an integer between 12 and 255 bytes.');
            }
            $schemes = $schemes->withReplacement(new AesGcmEncryptionScheme((int) $ivLength));
        }

        return KmipKms::fromTls(
            $host,
            $clientCertificateFile,
            $clientPrivateKeyFile,
            $version,
            $dsn->port ?? self::DEFAULT_PORT,
            $dsn->getOption('ca'),
            $dsn->getOption('peer_name'),
            $dsn->getOption('passphrase'),
            $dsn->user,
            $dsn->password,
            (float) $timeout,
            $schemes,
            $cipher,
        );
    }
}
