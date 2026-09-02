<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Transport;

use Amp\Mysql\MysqlConfig;
use Amp\Mysql\MysqlConnectionPool;
use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnectionPool;
use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\Sql\SqlTransactionIsolationLevel;
use Fabpot\Amp\Sqlite\SqliteConfig;
use Fabpot\Amp\Sqlite\SqliteConnectionPool;
use Fabpot\Amp\Sqlite\SqliteTransactionMode;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\MysqlBackend;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\PostgresBackend;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\SqliteBackend;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;

/**
 * @implements TransportFactoryInterface<AmpSqlTransport>
 */
final class AmpSqlTransportFactory implements TransportFactoryInterface
{
    private const DEFAULT_OPTIONS = Connection::DEFAULT_OPTIONS + [
        'max_connections' => 10,
        'idle_timeout' => 60,
    ];

    public function createTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): AmpSqlTransport
    {
        unset($options['transport_name']);
        $scheme = strtok($dsn, ':');

        try {
            return match ($scheme) {
                'amp-sqlite' => $this->createSqliteTransport($dsn, $options, $serializer),
                'amp-mysql' => $this->createMysqlTransport($dsn, $options, $serializer),
                'amp-postgres' => $this->createPostgresTransport($dsn, $options, $serializer),
                default => throw new InvalidArgumentException('The given AMPHP SQL Messenger DSN is invalid.'),
            };
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $params = parse_url($dsn);
            $previous = false !== $params && !isset($params['user']) && !isset($params['pass']) ? $e : null;

            throw new InvalidArgumentException('The given AMPHP SQL Messenger DSN is invalid.', previous: $previous);
        }
    }

    public function supports(#[\SensitiveParameter] string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'amp-sqlite://') || str_starts_with($dsn, 'amp-mysql://') || str_starts_with($dsn, 'amp-postgres://');
    }

    /** @param array<string, mixed> $options */
    private function createSqliteTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): AmpSqlTransport
    {
        if ('amp-sqlite://' === $dsn) {
            throw new InvalidArgumentException('The AMPHP SQL Messenger DSN must contain a file database path.');
        }
        if (!class_exists(SqliteConnectionPool::class)) {
            throw new InvalidArgumentException('The "fabpot/amphp-sqlite3" package is required to use the amp-sqlite transport.');
        }

        $isAbsolutePath = str_starts_with($dsn, 'amp-sqlite:///');
        $parsedDsn = (string) preg_replace('#^amp-sqlite:///#', 'amp-sqlite://localhost/', $dsn);
        $params = $this->parseDsn($parsedDsn, 'amp-sqlite', false);
        if (isset($params['user']) || isset($params['port'])) {
            throw new InvalidArgumentException('The given AMPHP SQL Messenger DSN is invalid.');
        }
        $configuration = $this->configure($params, $options, ['busy_timeout']);
        $path = $params['path'] ?? '';
        if (($params['host'] ?? '') === 'localhost') {
            if (!$isAbsolutePath) {
                $path = substr($path, 1);
            }
        } elseif (isset($params['host'])) {
            $path = $params['host'].$path;
        }
        $path = rawurldecode($path);
        if ('\\' === \DIRECTORY_SEPARATOR && preg_match('#^/[A-Za-z]:/#', $path)) {
            $path = substr($path, 1);
        }
        if ('' === $path || ':memory:' === ltrim($path, '/')) {
            throw new InvalidArgumentException('The AMPHP SQL Messenger DSN must contain a file database path.');
        }
        $busyTimeout = self::nonNegativeInteger($configuration['busy_timeout'] ?? 5000, 'busy_timeout');
        $config = (new SqliteConfig($path))->withBusyTimeout($busyTimeout)->withTransactionMode(SqliteTransactionMode::Immediate);
        $pool = new SqliteConnectionPool($config, $configuration['max_connections'], $configuration['idle_timeout'], transactionIsolation: SqliteTransactionMode::Immediate);

        return new AmpSqlTransport(new Connection($pool, new SqliteBackend(), $configuration), $serializer);
    }

    /** @param array<string, mixed> $options */
    private function createMysqlTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): AmpSqlTransport
    {
        if (!class_exists(MysqlConnectionPool::class)) {
            throw new InvalidArgumentException('The "amphp/mysql" package is required to use the amp-mysql transport.');
        }
        $params = $this->parseDsn($dsn, 'amp-mysql', true);
        $configuration = $this->configure($params, $options, ['tls_ca', 'tls_cert', 'tls_key']);
        $context = null;
        if (isset($configuration['tls_ca']) || isset($configuration['tls_cert']) || isset($configuration['tls_key'])) {
            $tls = new ClientTlsContext($params['host']);
            if (isset($configuration['tls_ca'])) {
                $tls = $tls->withCaFile(self::nonEmptyString($configuration['tls_ca'], 'tls_ca'));
            }
            if (isset($configuration['tls_cert'])) {
                $tls = $tls->withCertificate(new Certificate(self::nonEmptyString($configuration['tls_cert'], 'tls_cert'), isset($configuration['tls_key']) ? self::nonEmptyString($configuration['tls_key'], 'tls_key') : null));
            } elseif (isset($configuration['tls_key'])) {
                throw new InvalidArgumentException('The "tls_key" option requires "tls_cert".');
            }
            $context = (new ConnectContext())->withTlsContext($tls);
        }
        $config = new MysqlConfig($params['host'], $params['port'] ?? MysqlConfig::DEFAULT_PORT, $params['user'] ?? null, $params['pass'] ?? null, $params['database'], $context);
        $pool = new MysqlConnectionPool($config, $configuration['max_connections'], $configuration['idle_timeout'], transactionIsolation: SqlTransactionIsolationLevel::Committed);

        return new AmpSqlTransport(new Connection($pool, new MysqlBackend(), $configuration), $serializer);
    }

    /** @param array<string, mixed> $options */
    private function createPostgresTransport(#[\SensitiveParameter] string $dsn, array $options, SerializerInterface $serializer): AmpSqlTransport
    {
        if (!class_exists(PostgresConnectionPool::class)) {
            throw new InvalidArgumentException('The "amphp/postgres" package is required to use the amp-postgres transport.');
        }
        $params = $this->parseDsn($dsn, 'amp-postgres', true);
        $configuration = $this->configure($params, $options, ['sslmode']);
        $sslmode = isset($configuration['sslmode']) ? self::nonEmptyString($configuration['sslmode'], 'sslmode') : null;
        $host = trim($params['host'], '[]');
        $config = new PostgresConfig($host, $params['port'] ?? PostgresConfig::DEFAULT_PORT, $params['user'] ?? null, $params['pass'] ?? null, $params['database'], sslMode: $sslmode);
        $pool = new PostgresConnectionPool($config, $configuration['max_connections'], $configuration['idle_timeout'], transactionIsolation: SqlTransactionIsolationLevel::Committed);

        return new AmpSqlTransport(new Connection($pool, new PostgresBackend(), $configuration), $serializer);
    }

    /**
     * @return array{scheme: string, host?: string, port?: int, user?: string, pass?: string, path?: string, query?: string, database?: string}
     */
    private function parseDsn(#[\SensitiveParameter] string $dsn, string $scheme, bool $databaseRequired): array
    {
        $params = parse_url($dsn);
        if (false === $params || ($params['scheme'] ?? null) !== $scheme || isset($params['fragment']) || !isset($params['host'])) {
            throw new InvalidArgumentException('The given AMPHP SQL Messenger DSN is invalid.');
        }
        if (isset($params['port']) && $params['port'] < 1) {
            throw new InvalidArgumentException('The given AMPHP SQL Messenger DSN has an invalid port.');
        }
        if ($databaseRequired) {
            $database = rawurldecode(ltrim($params['path'] ?? '', '/'));
            if ('' === $database) {
                throw new InvalidArgumentException('The AMPHP SQL Messenger DSN must contain a database name.');
            }
            $params['database'] = $database;
            if (!str_starts_with($params['host'], '[')) {
                $params['host'] = rawurldecode($params['host']);
            }
            if (isset($params['user'])) {
                $params['user'] = rawurldecode($params['user']);
            }
            if (isset($params['pass'])) {
                $params['pass'] = rawurldecode($params['pass']);
            }
        }

        /** @var array{scheme: string, host: string, port?: int, user?: string, pass?: string, path?: string, query?: string, database?: string} $parsedDsn */
        $parsedDsn = $params;

        return $parsedDsn;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $options
     * @param list<string>         $backendOptions
     *
     * @return array{
     *     auto_setup: bool,
     *     queue_name: string,
     *     redeliver_timeout: int,
     *     table_name: string,
     *     max_connections: positive-int,
     *     idle_timeout: positive-int,
     *     ...<string, mixed>,
     * }
     */
    private function configure(#[\SensitiveParameter] array $params, array $options, array $backendOptions): array
    {
        $query = [];
        if (isset($params['query'])) {
            parse_str($params['query'], $query);
        }
        $allowed = [...array_keys(self::DEFAULT_OPTIONS), ...$backendOptions];
        foreach ([['options' => $options, 'location' => ''], ['options' => $query, 'location' => ' in DSN']] as $set) {
            if ($extra = array_diff(array_keys($set['options']), $allowed)) {
                throw new InvalidArgumentException(\sprintf('Unknown option found%s: [%s]. Allowed options are [%s].', $set['location'], implode(', ', $extra), implode(', ', $allowed)));
            }
        }
        $configuration = $query + $options + self::DEFAULT_OPTIONS;
        $configuration['auto_setup'] = self::boolean($configuration['auto_setup'], 'auto_setup');
        $configuration['redeliver_timeout'] = self::nonNegativeInteger($configuration['redeliver_timeout'], 'redeliver_timeout');
        $configuration['max_connections'] = self::positiveInteger($configuration['max_connections'], 'max_connections');
        $configuration['idle_timeout'] = self::positiveInteger($configuration['idle_timeout'], 'idle_timeout');
        $configuration['queue_name'] = self::nonEmptyString($configuration['queue_name'], 'queue_name');
        if (190 < \strlen($configuration['queue_name'])) {
            throw new InvalidArgumentException('The "queue_name" option must be at most 190 bytes long.');
        }
        $configuration['table_name'] = self::nonEmptyString($configuration['table_name'], 'table_name');
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,37}$/D', $configuration['table_name'])) {
            throw new InvalidArgumentException('The "table_name" option must be a valid unquoted SQL identifier of at most 38 characters.');
        }

        return $configuration;
    }

    private static function boolean(mixed $value, string $option): bool
    {
        if (null === $parsed = filter_var($value, \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE)) {
            throw new InvalidArgumentException(\sprintf('The "%s" option must be a boolean.', $option));
        }

        return $parsed;
    }

    private static function nonNegativeInteger(mixed $value, string $option): int
    {
        if (false === $parsed = filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new InvalidArgumentException(\sprintf('The "%s" option must be a non-negative integer.', $option));
        }

        return $parsed;
    }

    /** @return positive-int */
    private static function positiveInteger(mixed $value, string $option): int
    {
        if (false === $parsed = filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw new InvalidArgumentException(\sprintf('The "%s" option must be a positive integer.', $option));
        }

        return $parsed;
    }

    private static function nonEmptyString(mixed $value, string $option): string
    {
        if (!\is_string($value) || '' === $value) {
            throw new InvalidArgumentException(\sprintf('The "%s" option must be a non-empty string.', $option));
        }

        return $value;
    }
}
