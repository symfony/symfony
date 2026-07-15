<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\AmpSqlTransport;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\AmpSqlTransportFactory;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

final class AmpSqlTransportFactoryTest extends TestCase
{
    public function testSupports()
    {
        $factory = new AmpSqlTransportFactory();

        self::assertTrue($factory->supports('amp-sqlite:///tmp/messages.db', []));
        self::assertTrue($factory->supports('amp-mysql://localhost/app', []));
        self::assertTrue($factory->supports('amp-postgres://localhost/app', []));
        self::assertFalse($factory->supports('doctrine://default', []));
    }

    public function testCredentialsAreNotExposedByInvalidDsnError()
    {
        try {
            (new AmpSqlTransportFactory())->createTransport('amp-sqlite://user:secret@localhost/messages.db', [], new PhpSerializer());
            self::fail('Expected the DSN to be rejected.');
        } catch (InvalidArgumentException $e) {
            self::assertStringNotContainsString('user', $e->getMessage());
            self::assertStringNotContainsString('secret', $e->getMessage());
        }
    }

    #[DataProvider('provideInvalidDsnWithCredentials')]
    public function testCredentialsAreNotExposedByInvalidDsnTrace(#[\SensitiveParameter] string $dsn)
    {
        $previousIgnoreArgs = ini_set('zend.exception_ignore_args', '0');

        try {
            try {
                (new AmpSqlTransportFactory())->createTransport($dsn, [], new PhpSerializer());
                self::fail('Expected the DSN to be rejected.');
            } catch (InvalidArgumentException $e) {
                $traces = [];
                do {
                    foreach ($e->getTrace() as $frame) {
                        if (self::class === ($frame['class'] ?? null)) {
                            break;
                        }
                        $traces[] = $frame;
                    }
                } while ($e = $e->getPrevious());

                self::assertFalse(self::containsString($traces, 'secret'));
            }
        } finally {
            if (false !== $previousIgnoreArgs) {
                ini_set('zend.exception_ignore_args', $previousIgnoreArgs);
            }
        }
    }

    /** @return iterable<string, array{string}> */
    public static function provideInvalidDsnWithCredentials(): iterable
    {
        yield 'configuration error' => ['amp-mysql://user:secret@localhost/app?unknown=1'];
        yield 'driver configuration error' => ['amp-postgres://user:secret@localhost/app?sslmode=wrong'];
    }

    public function testCreatesTransportForRelativePath()
    {
        $transport = (new AmpSqlTransportFactory())->createTransport('amp-sqlite://var/messages.db', [], new PhpSerializer());

        try {
            self::assertInstanceOf(AmpSqlTransport::class, $transport);
        } finally {
            $transport->close();
        }
    }

    public function testCreatesTransportForAbsolutePath()
    {
        $path = sys_get_temp_dir().'/symfony-amp-sql-messenger-'.bin2hex(random_bytes(8)).'.db';
        $dsn = 'amp-sqlite:///'.ltrim(str_replace('\\', '/', $path), '/');
        $transport = (new AmpSqlTransportFactory())->createTransport($dsn, [], new PhpSerializer());

        try {
            $transport->setup();
            self::assertFileExists($path);
        } finally {
            $transport->close();
            @unlink($path);
        }
    }

    public function testPreservesPreviousExceptionWhenDsnIsInvalid()
    {
        try {
            (new AmpSqlTransportFactory())->createTransport('amp-postgres://localhost/app?sslmode=wrong', [], new PhpSerializer());
            self::fail('Expected the DSN to be rejected.');
        } catch (InvalidArgumentException $e) {
            self::assertSame('The given AMPHP SQL Messenger DSN is invalid.', $e->getMessage());
            self::assertInstanceOf(\Throwable::class, $e->getPrevious());
            self::assertStringContainsString('Invalid SSL mode', $e->getPrevious()->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('provideInvalidDsn')]
    public function testRejectsInvalidDsn(string $dsn, array $options, string $message)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        (new AmpSqlTransportFactory())->createTransport($dsn, $options, new PhpSerializer());
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function provideInvalidDsn(): iterable
    {
        yield 'missing path' => ['amp-sqlite://', [], 'must contain a file database path'];
        yield 'memory database' => ['amp-sqlite:///:memory:', [], 'must contain a file database path'];
        yield 'credentials' => ['amp-sqlite://user:secret@localhost/messages.db', [], 'DSN is invalid'];
        yield 'unknown option' => ['amp-sqlite://messages.db', ['unknown' => true], 'Unknown option found'];
        yield 'invalid table' => ['amp-sqlite://messages.db', ['table_name' => 'messages; DROP TABLE messages'], 'valid unquoted SQL identifier'];
        yield 'negative timeout' => ['amp-sqlite://messages.db', ['busy_timeout' => -1], 'non-negative integer'];
        yield 'mysql missing database' => ['amp-mysql://localhost', [], 'must contain a database name'];
        yield 'postgres missing database' => ['amp-postgres://localhost', [], 'must contain a database name'];
        yield 'postgres invalid SSL mode' => ['amp-postgres://localhost/app?sslmode=wrong', [], 'DSN is invalid'];
        yield 'mysql key without certificate' => ['amp-mysql://localhost/app?tls_key=/tmp/key.pem', [], 'requires "tls_cert"'];
    }

    private static function containsString(mixed $value, string $needle, ?\SplObjectStorage $seen = null): bool
    {
        if (\is_string($value)) {
            return str_contains($value, $needle);
        }
        if ($value instanceof \SensitiveParameterValue) {
            return false;
        }
        if (\is_object($value)) {
            $seen ??= new \SplObjectStorage();
            if ($seen->offsetExists($value)) {
                return false;
            }
            $seen[$value] = true;
            $value = (array) $value;
        }
        if (!\is_array($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (self::containsString($item, $needle, $seen)) {
                return true;
            }
        }

        return false;
    }
}
