<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Failure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Synchronizer\SingleDatabaseSynchronizer;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Messenger\Envelope;

class DbalFailedMessageStorage implements FailedMessageStorageInterface
{
    private $driverConnection;
    private $options;

    public function __construct(Connection $driverConnection, array $options)
    {
        $this->driverConnection = $driverConnection;
        $this->options = array_merge([
            'auto_setup' => true,
            'table_name' => 'failed_messages',
        ], $options);
    }

    public function add(Envelope $envelope, \Throwable $exception, string $transportName, \DateTimeInterface $failedAt): FailedMessage
    {
        $queryBuilder = $this->driverConnection->createQueryBuilder()
            ->insert($this->options['table_name'])
            ->values([
                'envelope' => ':envelope',
                'exception' => ':exception',
                'transport_name' => ':transport_name',
                'failed_at' => ':failed_at',
            ])
        ;

        $data = [
            'envelope' => serialize($envelope),
            'exception' => serialize($exception),
            'transport_name' => $transportName,
            'failed_at' => $failedAt->format('Y-m-d H:i:s'),
        ];

        $this->executeQuery($queryBuilder->getSQL(), $data);

        $data['id'] = $this->driverConnection->lastInsertId();

        return $this->createFailedMessage($data);
    }

    public function all(): array
    {
        $query = $this->driverConnection->createQueryBuilder()
            ->select('m.*')
            ->from($this->options['table_name'], 'm')
            ->orderBy('failed_at', 'ASC')
        ;

        $rows = $this->executeQuery($query->getSQL())->fetchAll();

        return array_map(function ($row) {
            return $this->createFailedMessage($row);
        }, $rows);
    }

    public function get($id): FailedMessage
    {
        $query = $this->driverConnection->createQueryBuilder()
            ->select('m.*')
            ->from($this->options['table_name'], 'm')
            ->where('m.id = :id')
        ;

        $row = $this->executeQuery($query->getSQL(), ['id' => $id])->fetch();

        return $this->createFailedMessage($row);
    }

    public function remove(FailedMessage $failedMessage): void
    {
        $query = $this->driverConnection->createQueryBuilder()
            ->delete($this->options['table_name'])
            ->where('m.id = :id')
        ;

        $this->executeQuery($query->getSQL(), ['id' => $failedMessage->getId()]);
    }

    public function removeAll(): void
    {
        $query = $this->driverConnection->createQueryBuilder()
            ->delete($this->options['table_name'])
        ;

        $this->executeQuery($query->getSQL());
    }

    private function executeQuery(string $sql, array $parameters = []): Statement
    {
        $stmt = null;

        try {
            $stmt = $this->driverConnection->prepare($sql);
            $stmt->execute($parameters);
        } catch (TableNotFoundException $e) {
            // create table
            if (!$this->driverConnection->isTransactionActive() && $this->options['auto_setup']) {
                $this->setup();
            }

            // statement not prepared ? SQLite throw on exception on prepare if the table does not exist
            if (null === $stmt) {
                $stmt = $this->driverConnection->prepare($sql);
            }

            $stmt->execute($parameters);
        }

        return $stmt;
    }

    public function setup(): void
    {
        $synchronizer = new SingleDatabaseSynchronizer($this->driverConnection);
        $synchronizer->updateSchema($this->getSchema(), true);
    }

    private function getSchema(): Schema
    {
        $schema = new Schema();
        $table = $schema->createTable($this->options['table_name']);
        $table->addColumn('id', Type::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);
        $table->addColumn('envelope', Type::TEXT)
            ->setNotnull(true);
        $table->addColumn('exception', Type::TEXT)
            ->setNotnull(true);
        $table->addColumn('transport_name', Type::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('failed_at', Type::DATETIME_IMMUTABLE)
            ->setNotnull(true);
        $table->setPrimaryKey(['id']);

        return $schema;
    }

    private function createFailedMessage(array $rowData): FailedMessage
    {
        return new FailedMessage(
            $rowData['id'],
            unserialize($rowData['envelope']),
            unserialize($rowData['exception']),
            $rowData['transport_name'],
            \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $rowData['failed_at'])
        );
    }
}
