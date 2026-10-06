<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\MongoDb\Transport;

use Composer\InstalledVersions;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\Exception as MongoDriverException;
use MongoDB\Driver\Session;
use MongoDB\Driver\WriteConcern;
use MongoDB\Model\BSONDocument;
use MongoDB\Operation\FindOneAndUpdate;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @internal
 *
 * @author Alessandro Lai <alessandro.lai85@gmail.com>
 */
class Connection
{
    private const DEFAULT_OPTIONS = [
        'database' => null,
        'collection_name' => 'messenger_messages',
        'queue_name' => 'default',
        'redeliver_timeout' => 3600,
    ];

    private string $uniqueId;

    public function __construct(
        private readonly Collection $collection,
        private readonly string $queueName = 'default',
        private readonly int $redeliverTimeout = 3600,
        private readonly ?ClockInterface $clock = null,
    ) {
        $this->uniqueId = 'consumer_'.bin2hex(random_bytes(16));
    }

    public static function fromDsn(#[\SensitiveParameter] string $dsn, array $options = [], ?Client $client = null, ?ClockInterface $clock = null): self
    {
        [$configuration, $uri] = self::buildConfiguration($dsn, $options);

        $client ??= new Client($uri, [], self::driverInfo());
        $collection = $client->getCollection($configuration['database'], $configuration['collection_name']);

        return new self($collection, $configuration['queue_name'], $configuration['redeliver_timeout'], $clock);
    }

    /**
     * Identifies the component in the driver handshake, so that the server
     * knows which part of the application opened the connection.
     */
    private static function driverInfo(): array
    {
        try {
            $version = (class_exists(InstalledVersions::class) ? InstalledVersions::getPrettyVersion('symfony/mongodb-messenger') : null) ?? 'unknown';
        } catch (\OutOfBoundsException) {
            $version = 'unknown';
        }

        return ['driver' => ['name' => 'symfony-mongodb-messenger', 'version' => $version]];
    }

    /**
     * Extracts the transport configuration from the DSN and the options, and
     * returns it along with the DSN stripped from the transport-specific
     * query parameters, ready to be passed to the MongoDB client.
     *
     * The database is read from the DSN path, the other settings from the
     * DSN query string or from the options, the latter taking precedence.
     * Query parameters that are not transport settings are kept and passed
     * to the MongoDB driver.
     *
     * @return array{0: array{database: string, collection_name: string, queue_name: string, redeliver_timeout: int}, 1: string}
     */
    public static function buildConfiguration(#[\SensitiveParameter] string $dsn, array $options = []): array
    {
        if (!str_starts_with($dsn, 'mongodb://') && !str_starts_with($dsn, 'mongodb+srv://')) {
            throw new InvalidArgumentException('The given MongoDB Messenger DSN is invalid. Expecting "mongodb://" or "mongodb+srv://".');
        }

        if (false === $components = parse_url($dsn)) {
            throw new InvalidArgumentException('The given MongoDB Messenger DSN is invalid.');
        }

        $query = [];
        if (isset($components['query'])) {
            parse_str($components['query'], $query);
        }

        if ($invalidOptions = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS))) {
            throw new InvalidArgumentException(\sprintf('Unknown option found: [%s]. Allowed options are [%s].', implode(', ', $invalidOptions), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        $configuration = $options + array_intersect_key($query, self::DEFAULT_OPTIONS) + self::DEFAULT_OPTIONS;
        $configuration['database'] ??= ltrim($components['path'] ?? '', '/') ?: null;

        if (null === $configuration['database']) {
            throw new InvalidArgumentException('The MongoDB Messenger transport requires a "database", provide it in the DSN path or as an option.');
        }

        if (!is_numeric($configuration['redeliver_timeout'])) {
            throw new InvalidArgumentException(\sprintf('The "redeliver_timeout" option must be an integer, "%s" given.', get_debug_type($configuration['redeliver_timeout'])));
        }
        $configuration['redeliver_timeout'] = (int) $configuration['redeliver_timeout'];

        foreach (array_keys(self::DEFAULT_OPTIONS) as $option) {
            $dsn = self::removeUriOption($dsn, $option);
        }

        return [$configuration, $dsn];
    }

    public function getUniqueId(): string
    {
        return $this->uniqueId;
    }

    /**
     * Returns the next available message, claimed with an atomic lock.
     *
     * The queues are served in FIFO order (sorted by availableAt), with no priority between them.
     *
     * @param list<string>|null $queueNames Defaults to the configured queue
     *
     * @throws TransportException
     */
    public function get(?array $queueNames = null): ?BSONDocument
    {
        $queueNames ??= [$this->queueName];

        if (!$queueNames) {
            throw new InvalidArgumentException('At least one queue name is required.');
        }

        $options = $this->getWriteOptions();
        $options['returnDocument'] = FindOneAndUpdate::RETURN_DOCUMENT_AFTER;
        $options['sort'] = [
            'availableAt' => 1,
        ];
        $options = $this->setTypeMapOption($options);

        $updateStatement = [
            '$set' => [
                'deliveredTo' => $this->uniqueId,
                'deliveredAt' => new UTCDateTime($this->now()),
            ],
        ];

        try {
            $updatedDocument = $this->collection->findOneAndUpdate($this->createAvailableMessagesQuery($queueNames), $updateStatement, $options);
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        if (!$updatedDocument instanceof BSONDocument) {
            return null;
        }

        if ($updatedDocument['deliveredTo'] !== $this->uniqueId) {
            // concurrency issue - some other consumer got to this message while we were updating it
            return null;
        }

        return $updatedDocument;
    }

    /**
     * @param array<string, string> $headers
     * @param int                   $delay     The delay in milliseconds
     * @param string|null           $queueName The queue to send to, defaults to the configured queue
     *
     * @return ObjectId The inserted id
     *
     * @throws TransportException
     */
    public function send(string $body, array $headers = [], int $delay = 0, ?Session $session = null, ?string $queueName = null): ObjectId
    {
        $document = $this->createDocument($this->now(), $body, $headers, $delay, $queueName);

        try {
            $insertResult = $this->collection->insertOne($document, $this->getWriteOptions($session));
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $insertResult->getInsertedId();
    }

    /**
     * Inserts messages with one request per run of consecutive messages that share the same session.
     *
     * The driver splits each request into as many commands as the size limits of the server require.
     *
     * @param non-empty-array<array{string, array<string, string>, int, ?Session, ?string}> $messages The arguments of send() for each message
     *
     * @return array{array<ObjectId>, array<TransportException>} The ids of the inserted messages and the exceptions that prevented inserting the others, by the key of the messages
     */
    public function sendBatch(array $messages): array
    {
        $now = $this->now();
        $ids = $requests = $documents = [];
        $session = null;

        foreach ($messages as $key => [$body, $headers, $delay, $messageSession, $queueName]) {
            if ($documents && $messageSession !== $session) {
                $requests[] = [$session, $documents];
                $documents = [];
            }

            $session = $messageSession;
            $document = $this->createDocument($now, $body, $headers, $delay, $queueName);
            // generated like the driver does, to know the ids of the documents inserted before a failure
            $document['_id'] = $ids[$key] = new ObjectId();
            $documents[$key] = $document;
        }

        $requests[] = [$session, $documents];
        $exceptions = [];

        foreach ($requests as $i => [$session, $documents]) {
            try {
                $this->collection->insertMany(array_values($documents), ['ordered' => false] + $this->getWriteOptions($session));
            } catch (MongoDriverException $e) {
                $keys = array_keys($documents);
                // a write concern error fails all the documents, as it fails send()
                $result = $e instanceof BulkWriteException && !$e->getWriteResult()->getWriteConcernError() ? $e->getWriteResult() : null;

                foreach ($result?->getWriteErrors() ?? [] as $error) {
                    $exceptions[$keys[$error->getIndex()]] = new TransportException($error->getMessage());
                }

                // unordered inserts go on after a rejected document; the driver sends the documents in order until an error stops it
                $processed = $result ? $result->getInsertedCount() + \count($result->getWriteErrors()) : 0;

                if ($processed < \count($keys)) {
                    $exception = new TransportException($e->getMessage(), 0, $e);
                    $exceptions += array_fill_keys(\array_slice($keys, $processed), $exception);

                    foreach (\array_slice($requests, $i + 1) as [, $unsent]) {
                        $exceptions += array_fill_keys(array_keys($unsent), $exception);
                    }

                    break;
                }
            }
        }

        return [array_diff_key($ids, $exceptions), $exceptions];
    }

    /**
     * @param string $id The ID of the message to ack; the corresponding document will be removed from the collection
     *
     * @return bool Returns true if the document has been deleted
     *
     * @throws TransportException
     */
    public function ack(string $id): bool
    {
        try {
            $deleteResult = $this->collection->deleteOne(['_id' => new ObjectId($id)], $this->getWriteOptions());
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $deleteResult->getDeletedCount() > 0;
    }

    /**
     * @param string $id The ID of the message to reject; the corresponding document will be removed from the collection
     *
     * @return bool Returns true if the document has been deleted
     *
     * @throws TransportException
     */
    public function reject(string $id): bool
    {
        try {
            $deleteResult = $this->collection->deleteOne(['_id' => new ObjectId($id)], $this->getWriteOptions());
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $deleteResult->getDeletedCount() > 0;
    }

    /**
     * @throws TransportException
     */
    public function getMessageCount(): int
    {
        try {
            return $this->collection->countDocuments($this->createAvailableMessagesQuery());
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @throws TransportException
     */
    public function find(string $id): ?BSONDocument
    {
        try {
            $document = $this->collection->findOne(['_id' => new ObjectId($id)], $this->setTypeMapOption());
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $document instanceof BSONDocument ? $document : null;
    }

    /**
     * @return iterable<BSONDocument>
     *
     * @throws TransportException
     */
    public function findAll(?int $limit = null): iterable
    {
        $options = [];
        if (null !== $limit) {
            $options['limit'] = $limit;
        }

        try {
            return $this->collection->find($this->createAvailableMessagesQuery(), $this->setTypeMapOption($options));
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    public function deleteAll(): void
    {
        try {
            $this->collection->deleteMany(['queueName' => $this->queueName]);
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * Creates a compound index including the queueName, availableAt and
     * deliveredAt fields, to speed up the polling query.
     */
    public function setup(): void
    {
        try {
            $this->collection->createIndex([
                'availableAt' => 1,
                'queueName' => 1,
                'deliveredAt' => 1,
            ]);
        } catch (MongoDriverException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @param list<string>|null $queueNames Defaults to the configured queue
     *
     * @return array<string, mixed>
     */
    private function createAvailableMessagesQuery(?array $queueNames = null): array
    {
        $queueNames ??= [$this->queueName];

        $now = $this->now();
        $redeliverLimit = $now->modify(\sprintf('-%d seconds', $this->redeliverTimeout));

        return [
            '$or' => [
                ['deliveredAt' => null],
                ['deliveredAt' => [
                    '$lt' => new UTCDateTime($redeliverLimit),
                ]],
            ],
            'availableAt' => ['$lte' => new UTCDateTime($now)],
            'queueName' => ['$in' => $queueNames],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getWriteOptions(?Session $session = null): array
    {
        if (null === $session) {
            return ['writeConcern' => new WriteConcern(WriteConcern::MAJORITY)];
        }

        if ($session->isInTransaction()) {
            return ['session' => $session];
        }

        return ['session' => $session, 'writeConcern' => new WriteConcern(WriteConcern::MAJORITY)];
    }

    /**
     * @param array<string, mixed> $readOptions
     *
     * @return array<string, mixed>
     */
    private function setTypeMapOption(array $readOptions = []): array
    {
        $readOptions['typeMap'] = [
            'root' => BSONDocument::class,
        ];

        return $readOptions;
    }

    private function now(): \DateTimeImmutable
    {
        return $this->clock?->now() ?? new \DateTimeImmutable();
    }

    /**
     * @param array<string, string> $headers
     */
    private function createDocument(\DateTimeImmutable $now, string $body, array $headers, int $delay, ?string $queueName): BSONDocument
    {
        $availableAt = $now->modify(\sprintf('+%d milliseconds', $delay));

        $document = new BSONDocument();
        $document['body'] = $body;
        $document['headers'] = new BSONDocument($headers);
        $document['queueName'] = $queueName ?? $this->queueName;
        $document['createdAt'] = new UTCDateTime($now);
        $document['availableAt'] = new UTCDateTime($availableAt);

        return $document;
    }

    private static function removeUriOption(string $uri, string $option): string
    {
        if (preg_match('/^(.*[?&])'.$option.'=[^&#]*&?(([^#]*).*)$/', $uri, $matches)) {
            $prefix = $matches[1];
            if ('' === $matches[3]) {
                $prefix = substr($prefix, 0, -1);
            }
            $uri = $prefix.$matches[2];
        }

        return $uri;
    }
}
