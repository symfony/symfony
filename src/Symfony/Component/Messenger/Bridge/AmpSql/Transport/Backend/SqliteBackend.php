<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmpSql\Transport\Backend;

use Amp\Sql\SqlConnection;
use Amp\Sql\SqlTransaction;
use Symfony\Component\Messenger\Exception\TransportException;

/** @internal */
final class SqliteBackend implements BackendInterface
{
    public function getNowExpression(): string
    {
        return "CAST(unixepoch('subsec') * 1000 AS INTEGER)";
    }

    public function getClaimLockSql(): string
    {
        return '';
    }

    public function validateVersion(SqlConnection $connection): void
    {
        $version = $connection->query('SELECT sqlite_version() AS version')->fetchRow()['version'] ?? null;
        if (!\is_string($version)) {
            throw new TransportException('Could not determine the SQLite version.');
        }
        if (version_compare($version, '3.42.0', '<')) {
            throw new TransportException('AMPHP SQL requires SQLite 3.42 or newer.');
        }
    }

    public function setup(SqlConnection $connection, string $table): void
    {
        $connection->query(\sprintf(<<<'SQL'
            CREATE TABLE IF NOT EXISTS %s (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                body TEXT NOT NULL,
                headers TEXT NOT NULL,
                queue_name TEXT NOT NULL,
                created_at INTEGER NOT NULL,
                available_at INTEGER NOT NULL,
                delivered_at INTEGER DEFAULT NULL
            )
            SQL, $table));
        $connection->query(\sprintf('CREATE INDEX IF NOT EXISTS %1$s_available_idx ON %1$s (queue_name, available_at, delivered_at, id)', $table));
    }

    public function insert(SqlTransaction $transaction, string $table, string $body, string $headers, string $queueName, int $delay): mixed
    {
        $now = $this->getNowExpression();
        $transaction->execute(\sprintf('INSERT INTO %s (body, headers, queue_name, created_at, available_at) VALUES (?, ?, ?, %s, %s + ?)', $table, $now, $now), [$body, $headers, $queueName, $delay]);

        return $transaction->query('SELECT last_insert_rowid() AS id')->fetchRow()['id'] ?? null;
    }
}
