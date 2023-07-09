<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Doctrine\Transport;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\SetupableTransportInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 * @author Herberto Graca <herberto.graca@gmail.com>
 * @author Alexander Malyk <shu.rick.ifmo@gmail.com>
 */
class DoctrineTransport implements TransportInterface, SetupableTransportInterface, MessageCountAwareInterface, ListableReceiverInterface
{
    private Connection $connection;
    private SerializerInterface $serializer;
    private DoctrineReceiver $receiver;
    private DoctrineSender $sender;

    public function __construct(Connection $connection, SerializerInterface $serializer)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
    }

    public function __destruct()
    {
        // The worker using this transport might have pulled 50 msgs out of the mq, marking them as "in process",
        // but because of its options (ie --limit, --failure-limit, --memory-limit, --time-limit) it might terminate
        // before it actually handles them all, leaving messages in limbo where they will not be handled by the
        // consumer that pulled them, and they won't be picked up by any other consumer because they are
        // "in process" already.
        // Thus, when the consumer stops, and its transport gets destroyed, we need to put the messages not handled,
        // back in "waiting to be processed" so that they can be picked up by other consumers.
        $this->getReceiver()->undeliverNotHandled();
    }

    public function get(): iterable
    {
        return $this->getReceiver()->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->getReceiver()->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->getReceiver()->reject($envelope);
    }

    public function getMessageCount(): int
    {
        return $this->getReceiver()->getMessageCount();
    }

    public function all(int $limit = null): iterable
    {
        return $this->getReceiver()->all($limit);
    }

    public function find(mixed $id): ?Envelope
    {
        return $this->getReceiver()->find($id);
    }

    public function send(Envelope $envelope): Envelope
    {
        return $this->getSender()->send($envelope);
    }

    public function setup(): void
    {
        $this->connection->setup();
    }

    /**
     * Adds the Table to the Schema if this transport uses this connection.
     *
     * @param \Closure $isSameDatabase
     */
    public function configureSchema(Schema $schema, DbalConnection $forConnection/* , \Closure $isSameDatabase */): void
    {
        $isSameDatabase = 2 < \func_num_args() ? func_get_arg(2) : static fn () => false;

        $this->connection->configureSchema($schema, $forConnection, $isSameDatabase);
    }

    /**
     * Adds extra SQL if the given table was created by the Connection.
     *
     * @return string[]
     */
    public function getExtraSetupSqlForTable(Table $createdTable): array
    {
        return $this->connection->getExtraSetupSqlForTable($createdTable);
    }

    private function getReceiver(): DoctrineReceiver
    {
        return $this->receiver ??= new DoctrineReceiver($this->connection, $this->serializer);
    }

    private function getSender(): DoctrineSender
    {
        return $this->sender ??= new DoctrineSender($this->connection, $this->serializer);
    }
}
