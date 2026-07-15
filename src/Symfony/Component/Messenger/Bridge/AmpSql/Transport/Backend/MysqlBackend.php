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

use Amp\Mysql\MysqlResult;
use Amp\Sql\SqlConnection;
use Amp\Sql\SqlTransaction;
use Symfony\Component\Messenger\Exception\TransportException;

/** @internal */
final class MysqlBackend implements BackendInterface
{
    public function getNowExpression(): string
    {
        return 'CAST(ROUND(UNIX_TIMESTAMP(CURRENT_TIMESTAMP(3)) * 1000) AS SIGNED)';
    }

    public function getClaimLockSql(): string
    {
        return ' FOR UPDATE SKIP LOCKED';
    }

    public function validateVersion(SqlConnection $connection): void
    {
        $version = $connection->query('SELECT VERSION() AS version')->fetchRow()['version'] ?? null;
        if (!\is_string($version)) {
            throw new TransportException('Could not determine the MySQL server version.');
        }
        if (str_contains($version, 'MariaDB')) {
            if (!preg_match('/(\d+\.\d+\.\d+)-MariaDB/', $version, $matches)) {
                throw new TransportException('Could not determine the MariaDB server version.');
            }
            if (version_compare($matches[1], '10.6.0', '<')) {
                throw new TransportException('AMPHP SQL requires MariaDB 10.6 or newer.');
            }

            return;
        }
        if (version_compare($version, '8.0.1', '<')) {
            throw new TransportException('AMPHP SQL requires MySQL 8.0.1 or newer.');
        }
    }

    public function setup(SqlConnection $connection, string $table): void
    {
        $connection->query(\sprintf(<<<'SQL'
            CREATE TABLE IF NOT EXISTS %s (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                body LONGTEXT NOT NULL,
                headers LONGTEXT NOT NULL,
                queue_name VARBINARY(190) NOT NULL,
                created_at BIGINT NOT NULL,
                available_at BIGINT NOT NULL,
                delivered_at BIGINT DEFAULT NULL,
                INDEX %1$s_available_idx (queue_name, available_at, delivered_at, id)
            ) ENGINE=InnoDB
            SQL, $table));
    }

    public function insert(SqlTransaction $transaction, string $table, string $body, string $headers, string $queueName, int $delay): mixed
    {
        $now = $this->getNowExpression();
        $result = $transaction->execute(\sprintf('INSERT INTO %s (body, headers, queue_name, created_at, available_at) VALUES (?, ?, ?, %s, %s + ?)', $table, $now, $now), [$body, $headers, $queueName, $delay]);

        return $result instanceof MysqlResult ? $result->getLastInsertId() : null;
    }
}
