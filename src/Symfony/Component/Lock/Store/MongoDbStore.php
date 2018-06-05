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

use Symfony\Component\Lock\Exception\InvalidArgumentException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Exception\NotSupportedException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\StoreInterface;

/**
 * MongoDbStore is a StoreInterface implementation using MongoDB as store engine.
 *
 * @author Joe Bennett <joe@assimtech.com>
 */
class MongoDbStore implements StoreInterface
{
    private $mongo;
    private $options;
    private $collection;

    /**
     * @param \MongoDB\Client $mongo
     * @param array           $options
     *
     * database:    The name of the database [required]
     * collection:  The name of the collection [default: lock]
     * initialTtl:  The expiration delay of locks in seconds [default: 300.0]
     *
     * CAUTION: This store relies on all client and server nodes to have
     * synchronized clocks for lock expiry to occur at the correct time.
     * To ensure locks don't expire prematurely; the ttl's should be set with enough
     * extra time to account for any clock drift between nodes.
     *
     * writeConcern, readConcern and readPreference are not specified by MongoDbStore
     * meaning the collection's settings will take effect.
     * @see https://docs.mongodb.com/manual/applications/replication/
     *
     * Please note, the Symfony\Component\Lock\Key's $resource
     * must not exceed 1024 bytes including structural overhead.
     * @see https://docs.mongodb.com/manual/reference/limits/#Index-Key-Limit
     */
    public function __construct(\MongoDB\Client $mongo, array $options)
    {
        if (!isset($options['database'])) {
            throw new InvalidArgumentException(
                'You must provide the "database" option for MongoDBStore'
            );
        }

        $this->mongo = $mongo;

        $this->options = array_merge(array(
            'collection' => 'lock',
            'initialTtl' => 300.0,
        ), $options);
    }

    /**
     * Create a TTL index to automatically remove expired locks.
     *
     * This should be called once during database setup.
     *
     * Alternatively the TTL index can be created manually:
     *
     *  db.lock.ensureIndex(
     *      { "expires_at": 1 },
     *      { "expireAfterSeconds": 0 }
     *  )
     *
     * A TTL index MUST BE used on MongoDB 2.2+ to automatically clean up expired locks.
     *
     * @see http://docs.mongodb.org/manual/tutorial/expire-data/
     */
    public function createTTLIndex(): bool
    {
        $keys = array(
            'expires_at' => 1,
        );
        $options = array(
            'expireAfterSeconds' => 0,
        );
        return $this->getCollection()->createIndex($keys, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        $token = $this->getToken($key);
        $now = microtime(true);

        $filter = array(
            '_id' => (string) $key,
            '$or' => array(
                array(
                    'token' => $token,
                ),
                array(
                    'expires_at' => array(
                        '$lte' => $this->createDateTime($now),
                    ),
                ),
            ),
        );

        $update = array(
            '$set' => array(
                '_id' => (string) $key,
                'token' => $token,
                'expires_at' => $this->createDateTime($now + $this->options['initialTtl']),
            ),
        );

        $options = array(
            'upsert' => true,
        );

        $key->reduceLifetime($this->options['initialTtl']);
        try {
            $this->getCollection()->updateOne($filter, $update, $options);
        } catch (\MongoDB\Driver\Exception\WriteException $e) {
            throw new LockConflictedException('Failed to acquire lock', 0, $e);
        } catch (\Exception $e) {
            throw new LockStorageException($e->getMessage(), 0, $e);
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to store the "%s" lock.', $key));
        }
    }

    public function waitAndSave(Key $key)
    {
        throw new NotSupportedException(sprintf(
            'The store "%s" does not supports blocking locks.',
            __CLASS__
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        $now = microtime(true);

        $filter = array(
            '_id' => (string) $key,
            'token' => $this->getToken($key),
            'expires_at' => array(
                '$gte' => $this->createDateTime($now),
            ),
        );

        $update = array(
            '$set' => array(
                '_id' => (string) $key,
                'expires_at' => $this->createDateTime($now + $ttl),
            ),
        );

        $options = array(
            'upsert' => true,
        );

        $key->reduceLifetime($ttl);
        try {
            $this->getCollection()->updateOne($filter, $update, $options);
        } catch (\MongoDB\Driver\Exception\WriteException $e) {
            throw new LockConflictedException('Failed to put off the expiration of the lock', 0, $e);
        } catch (\Exception $e) {
            throw new LockStorageException($e->getMessage(), 0, $e);
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf(
                'Failed to put off the expiration of the "%s" lock within the specified time.',
                $key
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        $filter = array(
            '_id' => (string) $key,
            'token' => $this->getToken($key),
        );

        $options = array();

        $this->getCollection()->deleteOne($filter, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key)
    {
        $filter = array(
            '_id' => (string) $key,
            'token' => $this->getToken($key),
            'expires_at' => array(
                '$gte' => $this->createDateTime(),
            ),
        );

        $doc = $this->getCollection()->findOne($filter);

        return null !== $doc;
    }

    private function getCollection(): \MongoDB\Collection
    {
        if (null === $this->collection) {
            $this->collection = $this->mongo->selectCollection(
                $this->options['database'],
                $this->options['collection']
            );
        }

        return $this->collection;
    }

    /**
     * @param float $seconds Seconds since 1970-01-01T00:00:00.000Z supporting millisecond precision. Defaults to now.
     *
     * @return \MongoDB\BSON\UTCDateTime
     */
    private function createDateTime(float $seconds = null): \MongoDB\BSON\UTCDateTime
    {
        if (null === $seconds) {
            $seconds = microtime(true);
        }

        $milliseconds = $seconds * 1000;

        return new \MongoDB\BSON\UTCDateTime($milliseconds);
    }

    /**
     * Retrieves an unique token for the given key.
     */
    private function getToken(Key $key): string
    {
        if (!$key->hasState(__CLASS__)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(__CLASS__, $token);
        }

        return $key->getState(__CLASS__);
    }
}
