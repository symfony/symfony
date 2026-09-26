<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\StreamTransport;
use Symfony\Component\KeyManagement\Bridge\Kmip\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

final class StreamTransportTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private const int DEFAULT_PORT = 5696;
    private const string VALID_RESPONSE_FRAME = '42007b010000000842007a0100000000';

    public function testFrameIsReadAcrossShortReads()
    {
        stream_wrapper_register('kmip-chunk', ChunkedResponseStream::class);
        try {
            ChunkedResponseStream::$data = hex2bin(self::VALID_RESPONSE_FRAME);
            $stream = fopen('kmip-chunk://response', 'r');
            $this->assertSame(ChunkedResponseStream::$data, StreamTransport::readFrame($stream));
            fclose($stream);
        } finally {
            stream_wrapper_unregister('kmip-chunk');
        }
    }

    public function testOversizedFrameIsRejectedBeforeReadingBody()
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, hex2bin('42007b017fffffff'));
        rewind($stream);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('length');
        try {
            StreamTransport::readFrame($stream);
        } finally {
            fclose($stream);
        }
    }

    #[DataProvider('invalidFrameHeaders')]
    public function testInvalidResponseFrameHeaderIsRejected(string $header)
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, hex2bin($header));
        rewind($stream);

        $this->expectException(RuntimeException::class);
        try {
            StreamTransport::readFrame($stream);
        } finally {
            fclose($stream);
        }
    }

    public static function invalidFrameHeaders(): iterable
    {
        yield 'request message tag' => ['4200780100000008'];
        yield 'wrong type' => ['42007b0800000008'];
        yield 'empty structure' => ['42007b0100000000'];
        yield 'unaligned structure' => ['42007b0100000001'];
        yield 'length with sign bit set' => ['42007b0180000000'];
    }

    public function testTruncatedFrameIsRejected()
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, hex2bin('42007b0100000008'));
        rewind($stream);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('early');
        try {
            StreamTransport::readFrame($stream);
        } finally {
            fclose($stream);
        }
    }

    public function testReadTimeoutIsReported()
    {
        if (!\function_exists('stream_socket_pair') || false === $streams = @stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, 0)) {
            $this->markTestSkipped('Unix socket pairs are unavailable.');
        }
        stream_set_timeout($streams[0], 0, 10_000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('timed out');
        try {
            StreamTransport::readFrame($streams[0]);
        } finally {
            fclose($streams[0]);
            fclose($streams[1]);
        }
    }

    public function testReadFrameRejectsAnExpiredExchangeDeadline()
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, hex2bin(self::VALID_RESPONSE_FRAME));
        rewind($stream);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('timed out');
        try {
            StreamTransport::readFrame($stream, microtime(true) - 1);
        } finally {
            fclose($stream);
        }
    }

    public function testReadFrameRejectsAStreamThatCannotSetATimeout()
    {
        stream_wrapper_register('kmip-chunk', ChunkedResponseStream::class);
        try {
            ChunkedResponseStream::$data = hex2bin(self::VALID_RESPONSE_FRAME);
            ChunkedResponseStream::$allowTimeout = false;
            $stream = fopen('kmip-chunk://response', 'r');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Could not set the KMIP transport timeout.');
            try {
                StreamTransport::readFrame($stream, microtime(true) + 1);
            } finally {
                fclose($stream);
            }
        } finally {
            ChunkedResponseStream::$allowTimeout = true;
            stream_wrapper_unregister('kmip-chunk');
        }
    }

    public function testReadFrameRejectsAResponseCompletedAfterTheExchangeDeadline()
    {
        stream_wrapper_register('kmip-chunk', ChunkedResponseStream::class);
        try {
            ChunkedResponseStream::$data = hex2bin(self::VALID_RESPONSE_FRAME);
            ChunkedResponseStream::$maxChunkLength = 8;
            ChunkedResponseStream::$bodyDelayMicroseconds = 20_000;
            $stream = fopen('kmip-chunk://response', 'r');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('timed out');
            try {
                StreamTransport::readFrame($stream, microtime(true) + 0.01);
            } finally {
                fclose($stream);
            }
        } finally {
            ChunkedResponseStream::$maxChunkLength = 2;
            ChunkedResponseStream::$bodyDelayMicroseconds = 0;
            stream_wrapper_unregister('kmip-chunk');
        }
    }

    public function testConnectionFailureIdentifiesTheEndpointWithoutExposingSecrets()
    {
        $transport = new StreamTransport('127.0.0.1', 0, __FILE__, __FILE__, __FILE__, '127.0.0.1', 'private-secret', 1.0);

        try {
            $transport->exchange('request');
            $this->fail('The connection must fail.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('127.0.0.1:0', $e->getMessage());
            $this->assertStringNotContainsString('private-secret', $e->getMessage());
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExchangeWritesRequestAndReadsResponseThroughVerifiedTlsContext()
    {
        require __DIR__.'/Fixtures/mock-tls-connection.php';
        stream_wrapper_register('kmip-duplex', DuplexKmipStream::class);
        try {
            DuplexKmipStream::$response = hex2bin(self::VALID_RESPONSE_FRAME);
            DuplexKmipStream::$request = '';
            DuplexKmipStream::$closed = false;
            $transport = new StreamTransport('localhost', self::DEFAULT_PORT, __FILE__, __FILE__, __FILE__, 'kmip.example', 'private-secret', 1.0);

            $this->assertSame(DuplexKmipStream::$response, $transport->exchange('request'));
            $this->assertSame('request', DuplexKmipStream::$request);
            $this->assertTrue(DuplexKmipStream::$closed);
            $this->assertSame('tls://localhost:5696', DuplexKmipStream::$address);
            $this->assertSame(1.0, DuplexKmipStream::$timeout);
            $this->assertSame(__FILE__, DuplexKmipStream::$tlsOptions['local_cert']);
            $this->assertSame(__FILE__, DuplexKmipStream::$tlsOptions['local_pk']);
            $this->assertSame(__FILE__, DuplexKmipStream::$tlsOptions['cafile']);
            $this->assertSame('kmip.example', DuplexKmipStream::$tlsOptions['peer_name']);
            $this->assertSame('private-secret', DuplexKmipStream::$tlsOptions['passphrase']);
            $this->assertTrue(DuplexKmipStream::$tlsOptions['verify_peer']);
            $this->assertTrue(DuplexKmipStream::$tlsOptions['verify_peer_name']);
            $this->assertFalse(DuplexKmipStream::$tlsOptions['allow_self_signed']);
            $this->assertSame(\STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | \STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT, DuplexKmipStream::$tlsOptions['crypto_method']);
        } finally {
            stream_wrapper_unregister('kmip-duplex');
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTlsVerificationFailureReportsTheWarningInsteadOfSocketErrorZero()
    {
        require __DIR__.'/Fixtures/mock-tls-connection.php';
        DuplexKmipStream::$connectionWarning = 'SSL operation failed: certificate verify failed';
        $transport = new StreamTransport('localhost', self::DEFAULT_PORT, __FILE__, __FILE__, __FILE__, 'kmip.example', null, 1.0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('certificate verify failed');
        $transport->exchange('request');
    }

    public function testRequestIsWrittenAcrossShortWrites()
    {
        stream_wrapper_register('kmip-write', ChunkedRequestStream::class);
        try {
            ChunkedRequestStream::$data = '';
            $stream = fopen('kmip-write://request', 'w');
            StreamTransport::writeAll($stream, 'abcdefghijk');
            fclose($stream);
            $this->assertSame('abcdefghijk', ChunkedRequestStream::$data);
        } finally {
            stream_wrapper_unregister('kmip-write');
        }
    }

    public function testWriteCompletedAfterTheExchangeDeadlineHasUnknownOutcome()
    {
        stream_wrapper_register('kmip-write', ChunkedRequestStream::class);
        try {
            ChunkedRequestStream::$data = '';
            ChunkedRequestStream::$writeDelayMicroseconds = 20_000;
            $stream = fopen('kmip-write://request', 'w');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Its outcome is unknown.');
            try {
                StreamTransport::writeAll($stream, 'request', microtime(true) + 0.01);
            } finally {
                fclose($stream);
            }
        } finally {
            ChunkedRequestStream::$writeDelayMicroseconds = 0;
            stream_wrapper_unregister('kmip-write');
        }
    }

    public function testFailedWriteReportsThatTheRequestOutcomeIsUnknown()
    {
        $stream = fopen('php://temp', 'r');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Could not write the KMIP request. Its outcome is unknown.');
        try {
            StreamTransport::writeAll($stream, 'request');
        } finally {
            fclose($stream);
        }
    }

    public function testFailedWriteRedactsTheRequestFromItsExceptionTrace()
    {
        $stream = fopen('php://temp', 'r');
        try {
            $secret = 'kmip-request-marker';
            $trace = self::traceOf(static fn () => StreamTransport::writeAll($stream, $secret));

            self::assertRedacted($secret, $trace);
        } finally {
            fclose($stream);
        }
    }
}

final class ChunkedResponseStream
{
    public $context;

    public static string $data = '';
    public static int $maxChunkLength = 2;
    public static int $bodyDelayMicroseconds = 0;
    public static bool $allowTimeout = true;

    private int $offset = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        if ($this->offset >= 8 && self::$bodyDelayMicroseconds > 0) {
            usleep(self::$bodyDelayMicroseconds);
        }
        $chunk = substr(self::$data, $this->offset, min($count, self::$maxChunkLength));
        $this->offset += \strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->offset >= \strlen(self::$data);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return self::$allowTimeout;
    }

    public function stream_stat(): array
    {
        return [];
    }
}

final class ChunkedRequestStream
{
    private const int MAX_CHUNK_LENGTH = 2;

    public $context;
    public static string $data = '';
    public static int $writeDelayMicroseconds = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        if (self::$writeDelayMicroseconds > 0) {
            usleep(self::$writeDelayMicroseconds);
        }
        $chunk = substr($data, 0, self::MAX_CHUNK_LENGTH);
        self::$data .= $chunk;

        return \strlen($chunk);
    }

    public function stream_eof(): bool
    {
        return false;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return true;
    }

    public function stream_stat(): array
    {
        return [];
    }
}

final class DuplexKmipStream
{
    public $context;

    public static ?string $connectionWarning = null;
    public static string $address = '';
    public static float $timeout = 0;

    public static array $tlsOptions = [];

    public static string $response = '';
    public static string $request = '';
    public static bool $closed = false;

    private int $offset = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_read(int $count): string
    {
        $data = substr(self::$response, $this->offset, $count);
        $this->offset += \strlen($data);

        return $data;
    }

    public function stream_write(string $data): int
    {
        self::$request .= $data;

        return \strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->offset >= \strlen(self::$response);
    }

    public function stream_close(): void
    {
        self::$closed = true;
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return true;
    }

    public function stream_stat(): array
    {
        return [];
    }
}
