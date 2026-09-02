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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Bridge\AmpSql\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\AmpSqlTransport;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\AmpSqlTransportFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;

use function Amp\async;

#[Group('integration')]
final class AmpSqlDatabaseIntegrationTest extends TestCase
{
    #[DataProvider('provideServerDsn')]
    public function testConcurrentConsumersDoNotClaimTheSameMessages(string $dsnEnvironmentVariable, string $defaultDsn)
    {
        $dsn = getenv($dsnEnvironmentVariable);
        $required = false !== $dsn;
        $dsn = $dsn ?: $defaultDsn;
        $parts = parse_url($dsn);
        if (false === $parts || !isset($parts['host'], $parts['scheme'])) {
            self::fail('The database DSN is invalid.');
        }

        $port = $parts['port'] ?? match ($parts['scheme']) {
            'amp-mysql' => 3306,
            'amp-postgres' => 5432,
        };
        $socket = @fsockopen(trim($parts['host'], '[]'), $port, $errorCode, $errorMessage, 0.1);
        if (false === $socket) {
            $message = \sprintf('%s is not available: %s (%d).', $parts['scheme'], $errorMessage, $errorCode);
            if ($required) {
                self::fail($message);
            }
            self::markTestSkipped($message);
        }
        fclose($socket);

        $options = [
            'queue_name' => 'test_'.bin2hex(random_bytes(6)),
            'table_name' => 'messenger_amp_test',
        ];
        $factory = new AmpSqlTransportFactory();
        $sender = $factory->createTransport($dsn, $options, new PhpSerializer());
        $firstConsumer = $factory->createTransport($dsn, $options, new PhpSerializer());
        $secondConsumer = $factory->createTransport($dsn, $options, new PhpSerializer());
        self::assertInstanceOf(AmpSqlTransport::class, $sender);
        self::assertInstanceOf(AmpSqlTransport::class, $firstConsumer);
        self::assertInstanceOf(AmpSqlTransport::class, $secondConsumer);

        try {
            foreach (range(1, 10) as $number) {
                $sender->send(new Envelope(new DummyMessage((string) $number)));
            }

            [$first, $second] = [
                async(static fn (): array => iterator_to_array($firstConsumer->get(10))),
                async(static fn (): array => iterator_to_array($secondConsumer->get(10))),
            ];
            $messages = [...$first->await(), ...$second->await()];
            $ids = array_map(static fn (Envelope $envelope): int|string|null => $envelope->last(TransportMessageIdStamp::class)?->getId(), $messages);

            self::assertCount(10, $messages);
            self::assertCount(10, array_unique($ids));
            foreach ($messages as $message) {
                $sender->ack($message);
            }
        } finally {
            $sender->close();
            $firstConsumer->close();
            $secondConsumer->close();
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function provideServerDsn(): iterable
    {
        yield 'MySQL' => ['AMP_SQL_MYSQL_DSN', 'amp-mysql://root:password@127.0.0.1:3306/messenger'];
        yield 'PostgreSQL' => ['AMP_SQL_POSTGRES_DSN', 'amp-postgres://postgres:password@127.0.0.1:5432/postgres'];
    }
}
