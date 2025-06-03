<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Store;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Exception\DriverRuntimeException;
use MongoDB\Exception\InvalidArgumentException as MongoInvalidArgumentException;
use MongoDB\Exception\UnsupportedException;
use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\InvalidTtlException;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * MongoDbStore is a StoreInterface implementation using MongoDB as a storage
 * engine. Support for MongoDB server >=2.2 due to use of TTL indexes.
 *
 * CAUTION: TTL Indexes are used so this store relies on all client and server
 * nodes to have synchronized clocks for lock expiry to occur at the correct
 * time. To ensure locks don't expire prematurely; the TTLs should be set with
 * enough extra time to account for any clock drift between nodes.
 *
 * CAUTION: The locked resource name is indexed in the _id field of the lock
 * collection. An indexed field's value in MongoDB can be a maximum of 1024
 * bytes in length inclusive of structural overhead.
 *
 * @see https://docs.mongodb.com/manual/reference/limits/#Index-Key-Limit
 *
 * @author Joe Bennett <joe@assimtech.com>
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class MongoDbStore implements PersistingStoreInterface
{
    use ExpiringStoreTrait;

    private Manager $manager;
    private string $namespace;
    private string $uri;
    private array $options;
    private float $initialTtl;

    /**
     * @param Collection|Client|Manager|string $mongo      An instance of a Collection or Client or URI @see https://docs.mongodb.com/manual/reference/connection-string/
     * @param array                            $options    See below
     * @param float                            $initialTtl The expiration delay of locks in seconds
     *
     * @throws InvalidArgumentException If required options are not provided
     * @throws InvalidTtlException      When the initial ttl is not valid
     *
     * Options:
     *      gcProbability: Should a TTL Index be created expressed as a probability from 0.0 to 1.0 [default: 0.001]
     *      database:      The name of the database [required when $mongo is a Client]
     *      collection:    The name of the collection [required when $mongo is a Client]
     *      uriOptions:    Array of uri options. [used when $mongo is a URI]
     *      driverOptions: Array of driver options. [used when $mongo is a URI]
     *
     * When using a URI string:
     *      The database is determined from the uri's path, otherwise the "database" option is used. To specify an alternate authentication database; "authSource" uriOption or querystring parameter must be used.
     *      The collection is determined from the uri's "collection" querystring parameter, otherwise the "collection" option is used.
     *
     * For example: mongodb://myuser:mypass@myhost/mydatabase?collection=mycollection
     *
     * @see https://docs.mongodb.com/php-library/current/reference/method/MongoDBClient__construct/
     *
     * If gcProbability is set to a value greater than 0.0 there is a chance
     * this store will attempt to create a TTL index on self::save().
     * If you prefer to create your TTL Index manually you can set gcProbability
     * to 0.0 and optionally leverage
     * self::createTtlIndex(int $expireAfterSeconds = 0).
     *
     * writeConcern and readConcern are not specified by MongoDbStore meaning the connection's settings will take effect.
     * readPreference is primary for all queries.
     * @see https://docs.mongodb.com/manual/applications/replication/
     */
    public function __construct(Collection|Database|Client|Manager|string $mongo, array $options = [], float $initialTtl = 300.0)
    {
        if (isset($options['gcProbablity'])) {
            trigger_deprecation('symfony/lock', '6.3', 'The "gcProbablity" option (notice the typo in its name) is deprecated in "%s"; use the "gcProbability" option instead.', __CLASS__);

            $options['gcProbability'] = $options['gcProbablity'];
            unset($options['gcProbablity']);
        }

        $this->options = array_merge([
            'gcProbability' => 0.001,
            'database' => null,
            'collection' => null,
            'uriOptions' => [],
            'driverOptions' => [],
        ], $options);

        $this->initialTtl = $initialTtl;

        if ($mongo instanceof Collection) {
            $this->options['database'] ??= $mongo->getDatabaseName();
            $this->options['collection'] ??= $mongo->getCollectionName();
            $this->manager = $mongo->getManager();
        } elseif ($mongo instanceof Database) {
            $this->options['database'] ??= $mongo->getDatabaseName();
            $this->manager = $mongo->getManager();
        } elseif ($mongo instanceof Client) {
            $this->manager = $mongo->getManager();
        } elseif ($mongo instanceof Manager) {
            $this->manager = $mongo;
        } else {
            $this->uri = $this->skimUri($mongo);
        }

        if (null === $this->options['database']) {
            throw new InvalidArgumentException(sprintf('"%s()" requires the "database" in the URI path or option.', __METHOD__));
        }
        if (null === $this->options['collection']) {
            throw new InvalidArgumentException(sprintf('"%s()" requires the "collection" in the URI querystring or option.', __METHOD__));
        }
        $this->namespace = $this->options['database'].'.'.$this->options['collection'];

        if ($this->options['gcProbability'] < 0.0 || $this->options['gcProbability'] > 1.0) {
            throw new InvalidArgumentException(sprintf('"%s()" gcProbability must be a float from 0.0 to 1.0, "%f" given.', __METHOD__, $this->options['gcProbability']));
        }

        if ($this->initialTtl <= 0) {
            throw new InvalidTtlException(sprintf('"%s()" expects a strictly positive TTL, got "%d".', __METHOD__, $this->initialTtl));
        }
    }

    /**
     * Extract default database and collection from given connection URI and remove collection querystring.
     *
     * Non-standard parameters are removed from the URI to improve libmongoc's re-use of connections.
     *
     * @see https://www.php.net/manual/en/mongodb.connection-handling.php
     */
    private function skimUri(string $uri): string
    {
        if (!str_starts_with($uri, 'mongodb://') && !str_starts_with($uri, 'mongodb+srv://')) {
            throw new InvalidArgumentException(sprintf('The given MongoDB Connection URI "%s" is invalid. Expecting "mongodb://" or "mongodb+srv://".', $uri));
        }

        if (false === $params = parse_url($uri)) {
            throw new InvalidArgumentException(sprintf('The given MongoDB Connection URI "%s" is invalid.', $uri));
        }
        $pathDb = ltrim($params['path'] ?? '', '/') ?: null;
        if (null !== $pathDb) {
            $this->options['database'] = $pathDb;
        }

        $matches = [];
        if (preg_match('/^(.*[\?&])collection=([^&#]*)&?(([^#]*).*)$/', $uri, $matches)) {
            $prefix = $matches[1];
            $this->options['collection'] = $matches[2];
            if (empty($matches[4])) {
                $prefix = substr($prefix, 0, -1);
            }
            $uri = $prefix.$matches[3];
        }

        return $uri;
    }

    /**
     * Creates a TTL index to automatically remove expired locks.
     *
     * If the gcProbability option is set higher than 0.0 (defaults to 0.001);
     * there is a chance this will be called on self::save().
     *
     * Otherwise; this should be called once manually during database setup.
     *
     * Alternatively the TTL index can be created manually on the database:
     *
     *  db.lock.createIndex(
     *      { "expires_at": 1 },
     *      { "expireAfterSeconds": 0 }
     *  )
     *
     * Please note, expires_at is based on the application server. If the
     * database time differs; a lock could be cleaned up before it has expired.
     * To ensure locks don't expire prematurely; the lock TTL should be set
     * with enough extra time to account for any clock drift between nodes.
     *
     * A TTL index MUST BE used to automatically clean up expired locks.
     *
     * @see http://docs.mongodb.org/manual/tutorial/expire-data/
     *
     * @return void
     *
     * @throws UnsupportedException          if options are not supported by the selected server
     * @throws MongoInvalidArgumentException for parameter/option parsing errors
     * @throws DriverRuntimeException        for other driver errors (e.g. connection errors)
     */
    public function createTtlIndex(int $expireAfterSeconds = 0)
    {
        $server = $this->getManager()->selectServer();
        $server->executeCommand($this->options['database'], new Command([
            'createIndexes' => $this->options['collection'],
            'indexes' => [
                [
                    'key' => [
                        'expires_at' => 1,
                    ],
                    'name' => 'expires_at_1',
                    'expireAfterSeconds' => $expireAfterSeconds,
                ],
            ],
        ]));
    }

    /**
     * @return void
     *
     * @throws LockExpiredException when save is called on an expired lock
     */
    public function save(Key $key)
    {
        $key->reduceLifetime($this->initialTtl);

        try {
            $this->upsert($key, $this->initialTtl);
        } catch (BulkWriteException $e) {
            if ($this->isDuplicateKeyException($e)) {
                throw new LockConflictedException('Lock was acquired by someone else.', 0, $e);
            }
            throw new LockAcquiringException('Failed to acquire lock.', 0, $e);
        }

        if ($this->options['gcProbability'] > 0.0 && (1.0 === $this->options['gcProbability'] || (random_int(0, \PHP_INT_MAX) / \PHP_INT_MAX) <= $this->options['gcProbability'])) {
            $this->createTtlIndex();
        }

        $this->checkNotExpired($key);
    }

    /**
     * @return void
     *
     * @throws LockStorageException
     * @throws LockExpiredException
     */
    public function putOffExpiration(Key $key, float $ttl)
    {
        $key->reduceLifetime($ttl);

        try {
            $this->upsert($key, $ttl);
        } catch (BulkWriteException $e) {
            if ($this->isDuplicateKeyException($e)) {
                throw new LockConflictedException('Failed to put off the expiration of the lock.', 0, $e);
            }
            throw new LockStorageException($e->getMessage(), 0, $e);
        }

        $this->checkNotExpired($key);
    }

    /**
     * @return void
     */
    public function delete(Key $key)
    {
        $write = new BulkWrite();
        $write->delete(
            [
                '_id' => (string) $key,
                'token' => $this->getUniqueToken($key),
            ],
            ['limit' => 1]
        );

        $this->getManager()->executeBulkWrite($this->namespace, $write);
    }

    public function exists(Key $key): bool
    {
        $cursor = $this->manager->executeQuery($this->namespace, new Query(
            [
                '_id' => (string) $key,
                'token' => $this->getUniqueToken($key),
                'expires_at' => [
                    '$gt' => $this->createMongoDateTime(microtime(true)),
                ],
            ],
            [
                'limit' => 1,
                'projection' => ['_id' => 1],
            ]
        ));

        return [] !== $cursor->toArray();
    }

    /**
     * Update or Insert a Key.
     *
     * @param float $ttl Expiry in seconds from now
     */
    private function upsert(Key $key, float $ttl): void
    {
        $now = microtime(true);
        $token = $this->getUniqueToken($key);

        $write = new BulkWrite();
        $write->update(
            [
                '_id' => (string) $key,
                '$or' => [
                    [
                        'token' => $token,
                    ],
                    [
                        'expires_at' => [
                            '$lte' => $this->createMongoDateTime($now),
                        ],
                    ],
                ],
            ],
            [
                '$set' => [
                    '_id' => (string) $key,
                    'token' => $token,
                    'expires_at' => $this->createMongoDateTime($now + $ttl),
                ],
            ],
            [
                'upsert' => true,
            ]
        );

        $this->getManager()->executeBulkWrite($this->namespace, $write);
    }

    private function isDuplicateKeyException(BulkWriteException $e): bool
    {
        $code = $e->getCode();

        $writeErrors = $e->getWriteResult()->getWriteErrors();
        if (1 === \count($writeErrors)) {
            $code = $writeErrors[0]->getCode();
        }

        // Mongo error E11000 - DuplicateKey
        return 11000 === $code;
    }

    private function getManager(): Manager
    {
        return $this->manager ??= new Manager($this->uri, $this->options['uriOptions'], $this->options['driverOptions']);
    }

    /**
     * @param float $seconds Seconds since 1970-01-01T00:00:00.000Z supporting millisecond precision. Defaults to now.
     */
    private function createMongoDateTime(float $seconds): UTCDateTime
    {
        return new UTCDateTime((int) ($seconds * 1000));
    }

    /**
     * Retrieves a unique token for the given key namespaced to this store.
     *
     * @param Key $key lock state container
     */
    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }
}
