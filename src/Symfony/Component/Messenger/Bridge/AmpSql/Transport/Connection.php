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

use Amp\Mysql\MysqlConnectionPool;
use Amp\Postgres\PostgresConnectionPool;
use Amp\Sql\SqlConnection;
use Amp\Sql\SqlConnectionException;
use Amp\Sql\SqlException;
use Amp\Sql\SqlQueryError;
use Amp\Sql\SqlTransaction;
use Amp\Sql\SqlTransactionError;
use Fabpot\Amp\Sqlite\SqliteConnectionPool;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend\BackendInterface;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @internal
 */
final class Connection
{
    public const DEFAULT_OPTIONS = [
        'auto_setup' => true,
        'queue_name' => 'default',
        'redeliver_timeout' => 3600,
        'table_name' => 'messenger_amp_messages',
    ];

    private bool $autoSetup;
    private string $queueName;
    private int $redeliverTimeout;
    private string $tableName;
    private bool $versionValidated = false;

    /**
     * @param SqlConnection|MysqlConnectionPool|PostgresConnectionPool|SqliteConnectionPool $connection
     * @param array<string, mixed>                                                          $configuration
     */
    public function __construct(
        private SqlConnection $connection,
        private BackendInterface $backend,
        array $configuration = [],
    ) {
        $configuration = array_replace(self::DEFAULT_OPTIONS, $configuration);
        $this->autoSetup = $configuration['auto_setup'];
        $this->queueName = $configuration['queue_name'];
        $this->redeliverTimeout = $configuration['redeliver_timeout'];
        $this->tableName = $configuration['table_name'];
    }

    /** @param array<string, string> $headers */
    public function send(#[\SensitiveParameter] string $body, #[\SensitiveParameter] array $headers, int $delay = 0): int|string
    {
        $this->ensureSetup();
        $transaction = null;

        try {
            $transaction = $this->connection->beginTransaction();
            $id = self::normalizeId($this->backend->insert($transaction, $this->tableName, base64_encode($body), json_encode($headers, \JSON_THROW_ON_ERROR), $this->queueName, $delay));
            $transaction->commit();

            return $id;
        } catch (\Throwable $e) {
            $this->rollback($transaction);
            throw $this->createTransportException('Could not send the message to AMPHP SQL.', $e);
        }
    }

    /** @return list<array{id: int|string, body: string, headers: array<string, string>}> */
    public function get(int $fetchSize = 1): array
    {
        $this->ensureSetup();
        $transaction = null;

        try {
            $transaction = $this->connection->beginTransaction();
            $now = $this->backend->getNowExpression();
            [$criteria, $params] = $this->getAvailabilityCriteria($now);
            $result = $transaction->execute(
                \sprintf('SELECT id, body, headers FROM %s WHERE %s ORDER BY available_at ASC, id ASC LIMIT ?%s', $this->tableName, $criteria, $this->backend->getClaimLockSql()),
                [...$params, max(1, $fetchSize)],
            );

            $rows = iterator_to_array($result, false);
            if ($rows) {
                $placeholders = implode(', ', array_fill(0, \count($rows), '?'));
                $transaction->execute(
                    \sprintf('UPDATE %s SET delivered_at = %s WHERE id IN (%s)', $this->tableName, $now, $placeholders),
                    array_column($rows, 'id'),
                );
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $this->rollback($transaction);
            throw $this->createTransportException('Could not receive messages from AMPHP SQL.', $e);
        }

        try {
            // rows are normalized after the claim is committed so that an undecodable
            // row is claimed anyway and cannot block the queue until its claim expires
            return array_map(self::normalizeRow(...), $rows);
        } catch (\Throwable $e) {
            throw $this->createTransportException('Could not receive messages from AMPHP SQL.', $e);
        }
    }

    public function ack(int|string $id): void
    {
        $this->delete($id, 'acknowledge');
    }

    public function reject(int|string $id): void
    {
        $this->delete($id, 'reject');
    }

    public function keepalive(int|string $id, ?int $seconds = null): void
    {
        if (null !== $seconds && $this->redeliverTimeout < $seconds) {
            throw new TransportException(\sprintf('AMPHP SQL redeliver_timeout (%ds) cannot be smaller than the keepalive interval (%ds).', $this->redeliverTimeout, $seconds));
        }

        $this->ensureSetup();
        try {
            $this->connection->execute(
                \sprintf('UPDATE %s SET delivered_at = %s WHERE id = ? AND queue_name = ?', $this->tableName, $this->backend->getNowExpression()),
                [$id, $this->queueName],
            );
        } catch (\Throwable $e) {
            throw $this->createTransportException('Could not keep the AMPHP SQL message alive.', $e);
        }
    }

    public function setup(): void
    {
        try {
            $this->ensureSupportedVersion();
            $this->backend->setup($this->connection, $this->tableName);
            $this->autoSetup = false;
        } catch (\Throwable $e) {
            throw $this->createTransportException('Could not set up the AMPHP SQL transport.', $e);
        }
    }

    public function getMessageCount(): int
    {
        $this->ensureSetup();

        try {
            [$criteria, $params] = $this->getAvailabilityCriteria($this->backend->getNowExpression());
            $count = $this->connection->execute(
                \sprintf('SELECT COUNT(id) AS message_count FROM %s WHERE %s', $this->tableName, $criteria),
                $params,
            )->fetchRow()['message_count'] ?? 0;

            if (\is_int($count)) {
                return $count;
            }
            if (\is_string($count) && $count === (string) ($normalized = (int) $count) && 0 <= $normalized) {
                return $normalized;
            }

            throw new \UnexpectedValueException('The AMPHP SQL message count is invalid.');
        } catch (\Throwable $e) {
            throw $this->createTransportException('Could not count messages in AMPHP SQL.', $e);
        }
    }

    /** @return list<array{id: int|string, body: string, headers: array<string, string>}> */
    public function findAll(?int $limit = null): array
    {
        $this->ensureSetup();

        try {
            [$criteria, $params] = $this->getAvailabilityCriteria($this->backend->getNowExpression());
            $sql = \sprintf('SELECT id, body, headers FROM %s WHERE %s ORDER BY available_at ASC, id ASC', $this->tableName, $criteria);
            if (null !== $limit) {
                $sql .= ' LIMIT ?';
                $params[] = max(0, $limit);
            }

            $rows = [];
            foreach ($this->connection->execute($sql, $params) as $row) {
                $rows[] = self::normalizeRow($row);
            }

            return $rows;
        } catch (\Throwable $e) {
            throw $this->createTransportException('Could not list messages in AMPHP SQL.', $e);
        }
    }

    /** @return array{id: int|string, body: string, headers: array<string, string>}|null */
    public function find(mixed $id): ?array
    {
        if (!\is_int($id) && (!\is_string($id) || !ctype_digit($id))) {
            return null;
        }

        $this->ensureSetup();
        try {
            $row = $this->connection->execute(
                \sprintf('SELECT id, body, headers FROM %s WHERE id = ? AND queue_name = ?', $this->tableName),
                [$id, $this->queueName],
            )->fetchRow();

            return null === $row ? null : self::normalizeRow($row);
        } catch (\Throwable $e) {
            throw $this->createTransportException('Could not find the AMPHP SQL message.', $e);
        }
    }

    public function close(): void
    {
        $this->connection->close();
    }

    private function delete(int|string $id, string $operation): void
    {
        $this->ensureSetup();
        try {
            $this->connection->execute(\sprintf('DELETE FROM %s WHERE id = ? AND queue_name = ?', $this->tableName), [$id, $this->queueName]);
        } catch (\Throwable $e) {
            throw $this->createTransportException(\sprintf('Could not %s the AMPHP SQL message.', $operation), $e);
        }
    }

    private function ensureSetup(): void
    {
        if ($this->autoSetup) {
            $this->setup();

            return;
        }

        try {
            $this->ensureSupportedVersion();
        } catch (\Throwable $e) {
            throw $this->createTransportException('Could not initialize the AMPHP SQL transport.', $e);
        }
    }

    /** @return array{string, list<int|string>} */
    private function getAvailabilityCriteria(string $now): array
    {
        return [
            \sprintf('queue_name = ? AND available_at <= %s AND (delivered_at IS NULL OR delivered_at < %s - ?)', $now, $now),
            [$this->queueName, $this->redeliverTimeout * 1000],
        ];
    }

    private function ensureSupportedVersion(): void
    {
        if (!$this->versionValidated) {
            $this->backend->validateVersion($this->connection);
            $this->versionValidated = true;
        }
    }

    private function rollback(?SqlTransaction $transaction): void
    {
        if (null === $transaction || !$transaction->isActive()) {
            return;
        }

        try {
            $transaction->rollback();
        } catch (\Throwable) {
        }
    }

    private function createTransportException(string $message, #[\SensitiveParameter] \Throwable $previous): TransportException
    {
        if ($previous instanceof TransportException) {
            return $previous;
        }
        $previous = match (true) {
            $previous instanceof SqlQueryError => new SqlQueryError($previous->getMessage(), $previous->getQuery()),
            $previous instanceof SqlConnectionException => new SqlConnectionException($previous->getMessage(), $previous->getCode()),
            $previous instanceof SqlException => new SqlException($previous->getMessage(), $previous->getCode()),
            $previous instanceof SqlTransactionError => new SqlTransactionError($previous->getMessage(), $previous->getCode()),
            default => null,
        };

        return new TransportException($message, 0, $previous);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int|string, body: string, headers: array<string, string>}
     */
    private static function normalizeRow(array $row): array
    {
        $encodedBody = $row['body'] ?? null;
        if (!\is_string($encodedBody) || false === $body = base64_decode($encodedBody, true)) {
            throw new \UnexpectedValueException('The AMPHP SQL message has invalid storage types.');
        }
        $encodedHeaders = $row['headers'] ?? null;
        if (!\is_string($encodedHeaders)) {
            throw new \UnexpectedValueException('The AMPHP SQL message has invalid storage types.');
        }

        $headers = json_decode($encodedHeaders, true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($headers)) {
            throw new \UnexpectedValueException('The AMPHP SQL message headers are invalid.');
        }
        $normalizedHeaders = [];
        foreach ($headers as $name => $value) {
            if (!\is_string($value)) {
                throw new \UnexpectedValueException('The AMPHP SQL message headers are invalid.');
            }
            $normalizedHeaders[(string) $name] = $value;
        }

        return ['id' => self::normalizeId($row['id'] ?? null), 'body' => $body, 'headers' => $normalizedHeaders];
    }

    private static function normalizeId(mixed $id): int|string
    {
        if (\is_int($id) || (\is_string($id) && ctype_digit($id))) {
            return $id;
        }

        throw new \UnexpectedValueException('The AMPHP SQL message ID is invalid.');
    }
}
