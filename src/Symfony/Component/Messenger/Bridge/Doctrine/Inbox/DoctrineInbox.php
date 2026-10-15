<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Inbox;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableExistsException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\Identifier;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Inbox\InboxInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;

/**
 * Records the messages that consumers handle in a database table.
 *
 * A message can be recorded only in the database transaction of its handlers, on the connection of the inbox.
 * The record is committed with the changes of the handlers, or rolled back with them so that a retry handles the message again.
 * When two workers get the same message, the second worker waits until the transaction of the first one ends.
 */
final class DoctrineInbox implements InboxInterface, SetupableTransportInterface
{
    /**
     * @param int   $retention     The number of seconds a record is kept, which must exceed the time a transport can take to redeliver a message
     * @param float $gcProbability The probability, from 0 to 1, that recording a message removes the expired records of its consumer
     */
    public function __construct(
        private Connection $connection,
        private string $tableName = 'messenger_inbox',
        private int $retention = 604800,
        private float $gcProbability = 0.001,
    ) {
    }

    public function record(string $consumer, string $messageId): bool
    {
        if (!$this->connection->isTransactionActive()) {
            throw new LogicException(\sprintf('Cannot record message "%s" for "%s" outside of a database transaction. Run the handlers in a transaction on the connection of the inbox, as the "doctrine_transaction" middleware does.', $messageId, $consumer));
        }

        $now = \DateTimeImmutable::createFromTimestamp(microtime(true));

        // the nested transaction is a savepoint, so that a failed insert doesn't abort the transaction of the handlers on PostgreSQL
        $this->connection->beginTransaction();

        try {
            $this->connection->insert($this->tableName, ['consumer' => $consumer, 'message_id' => $messageId, 'handled_at' => $now], ['handled_at' => Types::DATETIME_IMMUTABLE]);
            $this->connection->commit();
        } catch (UniqueConstraintViolationException) {
            $this->connection->rollBack();

            return false;
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        if ($this->gcProbability > 0 && (1.0 === $this->gcProbability || (random_int(0, \PHP_INT_MAX) / \PHP_INT_MAX) <= $this->gcProbability)) {
            $this->connection->executeStatement("DELETE FROM $this->tableName WHERE consumer = ? AND handled_at < ?", [$consumer, $now->modify(\sprintf('-%d seconds', $this->retention))], [ParameterType::STRING, Types::DATETIME_IMMUTABLE]);
        }

        return true;
    }

    public function forget(string $consumer, string $messageId): void
    {
        $this->connection->delete($this->tableName, ['consumer' => $consumer, 'message_id' => $messageId]);
    }

    public function setup(): void
    {
        $schema = new Schema([], [], $this->connection->createSchemaManager()->createSchemaConfig());
        $table = $this->configureSchema($schema, $this->connection, static fn (): bool => true)->getTable($this->tableName);

        try {
            $this->connection->createSchemaManager()->createTable($table);
        } catch (TableExistsException) {
        }
    }

    /**
     * Adds the table of the inbox to the schema of a connection, when that connection reaches the database of the inbox.
     *
     * @param \Closure(\Closure(string): mixed): bool $isSameDatabase
     *
     * @param-immediately-invoked-callable $isSameDatabase
     */
    public function configureSchema(Schema $schema, Connection $forConnection, \Closure $isSameDatabase): Schema
    {
        if ($schema->hasTable($this->tableName)) {
            return $schema;
        }

        if ($forConnection !== $this->connection && !$isSameDatabase($this->connection->executeStatement(...))) {
            return $schema;
        }

        if (method_exists($schema, 'edit')) {
            return $schema->edit()->addTable($this->buildSchemaTable())->create();
        }

        $this->configureSchemaTable($schema->createTable($this->tableName));

        return $schema;
    }

    private function buildSchemaTable(): Table
    {
        return Table::editor()
            ->setUnquotedName($this->tableName)
            // MySQL 5.6 only supports 191 characters on an indexed column in utf8mb4 mode
            ->addColumn(Column::editor()->setUnquotedName('consumer')->setTypeName(Types::STRING)->setLength(190)->setNotNull(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('message_id')->setTypeName(Types::STRING)->setLength(190)->setNotNull(true)->create())
            ->addColumn(Column::editor()->setUnquotedName('handled_at')->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull(true)->create())
            ->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted('consumer')), new UnqualifiedName(Identifier::unquoted('message_id'))], true))
            ->addIndex(Index::editor()->setUnquotedColumnNames('consumer', 'handled_at'))
            ->create();
    }

    /**
     * To be removed when doctrine/dbal minimum is bumped to ^4.5.
     */
    private function configureSchemaTable(Table $table): void
    {
        // MySQL 5.6 only supports 191 characters on an indexed column in utf8mb4 mode
        $table->addColumn('consumer', Types::STRING, ['length' => 190, 'notnull' => true]);
        $table->addColumn('message_id', Types::STRING, ['length' => 190, 'notnull' => true]);
        $table->addColumn('handled_at', Types::DATETIME_IMMUTABLE, ['notnull' => true]);
        $table->addPrimaryKeyConstraint(new PrimaryKeyConstraint(null, [new UnqualifiedName(Identifier::unquoted('consumer')), new UnqualifiedName(Identifier::unquoted('message_id'))], true));
        $table->addIndex(['consumer', 'handled_at']);
    }
}
