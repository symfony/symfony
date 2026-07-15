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

use Amp\Parallel\Context\ProcessContextFactory;
use Amp\Sql\SqlQueryError;
use Fabpot\Amp\Sqlite\SqliteConfig;
use Fabpot\Amp\Sqlite\SqliteConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\AmpSqlSender;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\SqliteBackend;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

final class AmpSqlSenderTest extends TestCase
{
    public function testDatabaseErrorKeepsSafeDiagnostics()
    {
        $serializedBody = 'serialized-secret-body';
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willReturn(['body' => $serializedBody]);
        $connection = (new SqliteConnector(new ProcessContextFactory(childConnectTimeout: 30)))->connect(new SqliteConfig(':memory:'));
        $sender = new AmpSqlSender(new Connection($connection, new SqliteBackend(), ['auto_setup' => false]), $serializer);

        try {
            $sender->send(new Envelope(new \stdClass()));
            self::fail('Expected sending to fail.');
        } catch (TransportException $e) {
            self::assertInstanceOf(SqlQueryError::class, $e->getPrevious());
            self::assertStringNotContainsString($serializedBody, $e->getMessage());
            self::assertStringNotContainsString($serializedBody, $e->getPrevious()->getMessage());

            $traces = [];
            do {
                $traces[] = $e->getTrace();
            } while ($e = $e->getPrevious());
            self::assertFalse(self::containsString($traces, base64_encode($serializedBody)));
        } finally {
            $connection->close();
        }
    }

    public function testSerializationErrorDoesNotExposeMessageBody()
    {
        $serializedBody = 'serialized-secret-body';
        $serializer = $this->createStub(SerializerInterface::class);
        $serializer->method('encode')->willThrowException(new \RuntimeException($serializedBody));
        $connection = (new SqliteConnector(new ProcessContextFactory(childConnectTimeout: 30)))->connect(new SqliteConfig(':memory:'));
        $sender = new AmpSqlSender(new Connection($connection, new SqliteBackend()), $serializer);

        try {
            $sender->send(new Envelope(new \stdClass()));
            self::fail('Expected sending to fail.');
        } catch (TransportException $e) {
            self::assertStringNotContainsString($serializedBody, $e->getMessage());
            self::assertNull($e->getPrevious());
        } finally {
            $connection->close();
        }
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
