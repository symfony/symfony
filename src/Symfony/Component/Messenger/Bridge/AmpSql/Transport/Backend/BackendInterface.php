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

/** @internal */
interface BackendInterface
{
    public function getNowExpression(): string;

    public function getClaimLockSql(): string;

    public function validateVersion(SqlConnection $connection): void;

    public function setup(SqlConnection $connection, string $table): void;

    /**
     * Returns the inserted message ID as provided by the driver; the caller
     * validates and normalizes it.
     */
    public function insert(SqlTransaction $transaction, string $table, string $body, string $headers, string $queueName, int $delay): mixed;

    /**
     * Inserts several messages with one statement and returns their IDs as provided by the driver, in the order of the messages, or an empty list when the driver cannot tell the ID of each row.
     *
     * @param non-empty-list<array{string, string, int}> $messages The body, headers and delay of each message
     *
     * @return list<mixed>
     */
    public function insertBatch(SqlTransaction $transaction, string $table, array $messages, string $queueName): array;
}
