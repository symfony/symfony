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

use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\AesGcm;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolClient;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\ProtocolVersion;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\StreamTransport;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;

/**
 * Builds a {@see KmipKms} from a DSN of the form:
 *
 *     kmip://[<user>:<password>@]<host>[:<port>]?cert=<path>&key=<path>&version=<version>[&ca=<path>][&peer_name=<name>][&passphrase=<passphrase>][&iv_length=12][&timeout=10]
 *
 * A client certificate and private key are required even when a username and password are supplied.
 * The `ca` option names a certificate authority file used to verify the server certificate; omitting it uses the system trust store.
 * The `peer_name` option controls the expected server name. The DSN has no path.
 *
 * The KMIP version, 1.4 or 2.0, must be selected explicitly. The `iv_length` option sets the AES-GCM IV length of new ciphertexts, 12 bytes by default.
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
    private const int MAX_PORT = 65535;
    /**
     * Default connection timeout in seconds, represented as a DSN option string.
     */
    private const string DEFAULT_TIMEOUT = '10';
    private const string DSN_SCHEME = 'kmip';
    /**
     * @var list<string>
     */
    private const array SUPPORTED_OPTIONS = ['cert', 'key', 'version', 'iv_length', 'ca', 'peer_name', 'passphrase', 'timeout'];
    /**
     * @var list<string>
     */
    private const array STRING_OPTIONS = ['version', 'iv_length', 'ca', 'peer_name', 'passphrase', 'timeout'];
    /**
     * A hostname containing a slash, colon or whitespace must pass IP address validation.
     */
    private const string HOST_CHARACTERS_REQUIRING_IP_VALIDATION_PATTERN = '/[\/:\s]/';
    /**
     * An empty regular expression in Unicode mode checks UTF-8 validity.
     */
    private const string UTF8_VALIDATION_PATTERN = '//u';

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
        } elseif (preg_match(self::HOST_CHARACTERS_REQUIRING_IP_VALIDATION_PATTERN, $host) && !filter_var($host, \FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('The KMIP host is invalid.');
        }
        $port = $dsn->port ?? self::DEFAULT_PORT;
        if ($port < 1 || $port > self::MAX_PORT) {
            throw new InvalidArgumentException(\sprintf('The KMIP port must be between 1 and %d.', self::MAX_PORT));
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
        $certificateAuthorityFile = $dsn->getOption('ca');
        $peerName = $dsn->getOption('peer_name');
        if ('' === $certificateAuthorityFile || '' === $peerName) {
            throw new InvalidArgumentException('The "ca" and "peer_name" options of the "kmip://" DSN must be nonempty when set.');
        }
        $timeout = $dsn->getOption('timeout', self::DEFAULT_TIMEOUT);
        if (!is_numeric($timeout) || !is_finite((float) $timeout) || (float) $timeout <= 0) {
            throw new InvalidArgumentException('The "timeout" option of the "kmip://" DSN must be positive.');
        }
        if ((null === $dsn->user) !== (null === $dsn->password) || null !== $dsn->user && ('' === $dsn->user || '' === $dsn->password || !preg_match(self::UTF8_VALIDATION_PATTERN, $dsn->user) || !preg_match(self::UTF8_VALIDATION_PATTERN, $dsn->password))) {
            throw new InvalidArgumentException('The "kmip://" DSN requires both a nonempty UTF-8 username and password when either is set.');
        }
        $versionName = $dsn->getOption('version');
        $version = \is_string($versionName) ? ProtocolVersion::tryFrom($versionName) : null;
        if (null === $version) {
            $versions = array_map(static fn (ProtocolVersion $version): string => $version->value, ProtocolVersion::cases());
            $lastVersion = array_pop($versions);
            $versionList = $versions ? implode(', ', $versions).' and '.$lastVersion : $lastVersion;

            throw new InvalidArgumentException('Supported KMIP versions are '.$versionList.'.');
        }
        $ivLength = $dsn->getOption('iv_length', (string) AesGcm::DEFAULT_IV_LENGTH);
        if (!ctype_digit($ivLength)) {
            throw new InvalidArgumentException('The "iv_length" option of the "kmip://" DSN must be an integer between 12 and 255 bytes.');
        }

        $transport = new StreamTransport($host, $port, $clientCertificateFile, $clientPrivateKeyFile, $certificateAuthorityFile, $peerName ?? $host, $dsn->getOption('passphrase'), (float) $timeout);

        return new KmipKms(new ProtocolClient($transport, $version, $dsn->user, $dsn->password), (int) $ivLength);
    }
}
