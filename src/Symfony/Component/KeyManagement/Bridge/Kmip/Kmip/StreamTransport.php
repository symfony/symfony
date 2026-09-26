<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Kmip;

use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\KeyMaterial;

/**
 * Exchanges a KMIP request and a size-limited TTLV response over verified TLS.
 *
 * A readable client certificate and private key are required.
 * The server certificate is validated against the configured certificate authority file or the system trust store, and its peer name is checked.
 * Connections use TLS 1.2 or 1.3.
 *
 * Each exchange opens and closes its own connection. Writes handle short results, and reads require a complete response frame within the size limit. An interrupted write is not replayed because the server may already have processed the request.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class StreamTransport
{
    use KeyMaterial;

    private const int ITEM_HEADER_LENGTH = 8;
    private const int ITEM_ALIGNMENT = 8;
    private const int MICROSECONDS_PER_SECOND = 1_000_000;
    private const int MAX_CONNECTION_ERROR_LENGTH = 160;
    /**
     * PHP unpack() numbers unnamed values starting at one.
     */
    private const int UNPACKED_NUMBER_INDEX = 1;
    /**
     * The TTLV Response Message tag followed by the Structure type.
     */
    private const string RESPONSE_FRAME_PREFIX = "\x42\x00\x7B\x01";
    /**
     * PHP unpack() format for an unsigned 32-bit integer in network byte order.
     */
    private const string UINT32_BIG_ENDIAN_FORMAT = 'N';

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $clientCertificateFile,
        private readonly string $clientPrivateKeyFile,
        private readonly ?string $certificateAuthorityFile,
        private readonly string $peerName,
        #[\SensitiveParameter] ?string $passphrase,
        private readonly float $timeout,
    ) {
        if (null !== $passphrase) {
            $this->keepMaterial($passphrase);
        }
    }

    public function exchange(#[\SensitiveParameter] string $request): string
    {
        foreach (['cert' => $this->clientCertificateFile, 'key' => $this->clientPrivateKeyFile, 'ca' => $this->certificateAuthorityFile] as $option => $path) {
            if (null !== $path && (!is_file($path) || !is_readable($path))) {
                throw new InvalidArgumentException(\sprintf('The KMIP "%s" file is not readable.', $option));
            }
        }

        $tlsOptions = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'peer_name' => $this->peerName,
            'local_cert' => $this->clientCertificateFile,
            'local_pk' => $this->clientPrivateKeyFile,
            'crypto_method' => \STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | \STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        ];
        if (null !== $this->certificateAuthorityFile) {
            $tlsOptions['cafile'] = $this->certificateAuthorityFile;
        }
        if ($this->hasMaterial()) {
            $tlsOptions['passphrase'] = $this->material();
        }

        $host = str_contains($this->host, ':') ? '['.$this->host.']' : $this->host;
        $deadline = microtime(true) + $this->timeout;
        $warning = null;
        set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
            $warning ??= $message;

            return true;
        });
        try {
            $stream = stream_socket_client('tls://'.$host.':'.$this->port, $errno, $error, $this->timeout, \STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $tlsOptions]));
        } finally {
            restore_error_handler();
        }
        unset($tlsOptions);
        if (false === $stream) {
            $detail = $warning ?? ('' !== $error ? $error : (0 === $errno ? 'TLS handshake or certificate verification failed' : \sprintf('socket error %d', $errno)));
            $detail = substr(preg_replace('/[\x00-\x1F\x7F]/', ' ', $detail), 0, self::MAX_CONNECTION_ERROR_LENGTH);

            throw new RuntimeException(\sprintf('Could not establish a verified KMIP TLS connection to "%s" on port %d (%s).', $host, $this->port, $detail));
        }

        try {
            self::writeAll($stream, $request, $deadline);

            return self::readFrame($stream, $deadline);
        } finally {
            fclose($stream);
        }
    }

    public function __destruct()
    {
        $this->wipeMaterial();
    }

    /**
     * @param resource $stream
     */
    public static function readFrame($stream, ?float $deadline = null): string
    {
        $header = self::readExactly($stream, self::ITEM_HEADER_LENGTH, $deadline);
        if (self::RESPONSE_FRAME_PREFIX !== substr($header, 0, \strlen(self::RESPONSE_FRAME_PREFIX))) {
            throw new RuntimeException('Invalid KMIP response frame.');
        }
        $length = unpack(self::UINT32_BIG_ENDIAN_FORMAT, substr($header, \strlen(self::RESPONSE_FRAME_PREFIX), self::ITEM_HEADER_LENGTH - \strlen(self::RESPONSE_FRAME_PREFIX)))[self::UNPACKED_NUMBER_INDEX];
        if ($length <= 0 || $length > Ttlv::MAX_FRAME - self::ITEM_HEADER_LENGTH || 0 !== $length % self::ITEM_ALIGNMENT) {
            throw new RuntimeException('Invalid KMIP response frame length.');
        }

        return $header.self::readExactly($stream, $length, $deadline);
    }

    /**
     * @param resource $stream
     */
    public static function writeAll($stream, #[\SensitiveParameter] string $request, ?float $deadline = null): void
    {
        for ($offset = 0, $length = \strlen($request); $offset < $length; $offset += $written) {
            self::setRemainingTimeout($stream, $deadline, true);
            $written = @fwrite($stream, substr($request, $offset));
            if (false === $written || 0 === $written || self::timedOut($stream)) {
                throw new RuntimeException('Could not write the KMIP request. Its outcome is unknown.');
            }
            self::assertDeadline($deadline, true);
        }
    }

    /**
     * @param resource $stream
     */
    private static function readExactly($stream, int $length, ?float $deadline): string
    {
        $data = '';
        while (\strlen($data) < $length) {
            self::setRemainingTimeout($stream, $deadline, false);
            $part = @fread($stream, $length - \strlen($data));
            if (false === $part || '' === $part) {
                throw new RuntimeException(self::timedOut($stream) ? 'KMIP response timed out.' : 'KMIP response ended early.');
            }
            self::assertDeadline($deadline, false);
            $data .= $part;
        }

        return $data;
    }

    /**
     * @param resource $stream
     */
    private static function setRemainingTimeout($stream, ?float $deadline, bool $writing): void
    {
        if (null === $deadline) {
            return;
        }
        self::assertDeadline($deadline, $writing);
        $remaining = $deadline - microtime(true);
        $seconds = (int) $remaining;
        $microseconds = max(1, (int) (($remaining - $seconds) * self::MICROSECONDS_PER_SECOND));
        if (!stream_set_timeout($stream, $seconds, $microseconds)) {
            throw new RuntimeException('Could not set the KMIP transport timeout.');
        }
    }

    private static function assertDeadline(?float $deadline, bool $writing): void
    {
        if (null !== $deadline && microtime(true) >= $deadline) {
            throw new RuntimeException($writing ? 'Could not write the KMIP request. Its outcome is unknown.' : 'KMIP response timed out.');
        }
    }

    /**
     * @param resource $stream
     */
    private static function timedOut($stream): bool
    {
        return (stream_get_meta_data($stream) + ['timed_out' => false])['timed_out'];
    }
}
