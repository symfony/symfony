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
    private $initialTtl;
    private $collection;

    /**
     * @param \MongoDB\Client $mongo
     * @param array           $options
     *
     * database: The name of the database [required]
     * collection: The name of the collection [default: lock]
     * resource_field: The field name for storing the lock id [default: _id] MUST be uniquely indexed if you chage it
     * token_field: The field name for storing the lock token [default: token]
     * acquired_field: The field name for storing the acquisition timestamp [default: acquired_at]
     * expiry_field: The field name for storing the expiry-timestamp [default: expires_at].
     *
     * It is strongly recommended to put an index on the `expiry_field` for
     * garbage-collection. Alternatively it's possible to automatically expire
     * the locks in the database as described below:
     *
     * A TTL collections can be used on MongoDB 2.2+ to cleanup expired locks
     * automatically. Such an index can for example look like this:
     *
     *     db.<session-collection>.ensureIndex(
     *         { "<expiry-field>": 1 },
     *         { "expireAfterSeconds": 0 }
     *     )
     *
     * More details on: http://docs.mongodb.org/manual/tutorial/expire-data/
     * @param float $initialTtl The expiration delay of locks in seconds
     */
    public function __construct(\MongoDB\Client $mongo, array $options = array(), float $initialTtl = 300.0)
    {
        if (!isset($options['database'])) {
            throw new \InvalidArgumentException(
                'You must provide the "database" option for MongoDBStore'
            );
        }

        $this->mongo = $mongo;

        $this->options = array_merge(array(
            'collection' => 'lock',
            'resource_field' => '_id',
            'token_field' => 'token',
            'acquired_field' => 'acquired_at',
            'expiry_field' => 'expires_at',
        ), $options);

        $this->initialTtl = $initialTtl;
    }

    /**
     * {@inheritdoc}
     *
     * db.lock.update(
     *     {
     *         _id: "test",
     *         expires_at: {
     *             $lte : new Date()
     *         }
     *     },
     *     {
     *         _id: "test",
     *         token: {# unique token #},
     *         acquired: new Date(),
     *         expires_at: new Date({# now + ttl #})
     *     },
     *     {
     *         upsert: 1
     *     }
     * );
     */
    public function save(Key $key)
    {
        $expiry = $this->createDateTime(microtime(true) + $this->initialTtl);

        $filter = array(
            $this->options['resource_field'] => (string) $key,
            '$or' => array(
                array(
                    $this->options['token_field'] => $this->getToken($key),
                ),
                array(
                    $this->options['expiry_field'] => array(
                        '$lte' => $this->createDateTime(),
                    ),
                ),
            ),
        );

        $update = array(
            '$set' => array(
                $this->options['resource_field'] => (string) $key,
                $this->options['token_field'] => $this->getToken($key),
                $this->options['acquired_field'] => $this->createDateTime(),
                $this->options['expiry_field'] => $expiry,
            ),
        );

        $options = array(
            'upsert' => true,
        );

        $key->reduceLifetime($this->initialTtl);
        try {
            $this->getCollection()->updateOne($filter, $update, $options);
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            throw new LockConflictedException('Failed to acquire lock', 0, $e);
        }

        if ($key->isExpired()) {
            throw new LockExpiredException(sprintf('Failed to store the "%s" lock.', $key));
        }
    }

    public function waitAndSave(Key $key)
    {
        throw new InvalidArgumentException(sprintf(
            'The store "%s" does not supports blocking locks.',
            __CLASS__
        ));
    }

    /**
     * {@inheritdoc}
     *
     * db.lock.update(
     *     {
     *         _id: "test",
     *         token: {# unique token #},
     *         expires_at: {
     *             $gte : new Date()
     *         }
     *     },
     *     {
     *         _id: "test",
     *         expires_at: new Date({# now + ttl #})
     *     },
     *     {
     *         upsert: 1
     *     }
     * );
     */
    public function putOffExpiration(Key $key, $ttl)
    {
        $expiry = $this->createDateTime(microtime(true) + $ttl);

        $filter = array(
            $this->options['resource_field'] => (string) $key,
            $this->options['token_field'] => $this->getToken($key),
            $this->options['expiry_field'] => array(
                '$gte' => $this->createDateTime(),
            ),
        );

        $update = array(
            '$set' => array(
                $this->options['resource_field'] => (string) $key,
                $this->options['expiry_field'] => $expiry,
            ),
        );

        $options = array(
            'upsert' => true,
        );

        $key->reduceLifetime($ttl);
        try {
            $this->getCollection()->updateOne($filter, $update, $options);
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            throw new LockConflictedException('Failed to put off the expiration of the lock', 0, $e);
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
     *
     * db.lock.remove({
     *     _id: "test"
     * });
     */
    public function delete(Key $key)
    {
        $filter = array(
            $this->options['resource_field'] => (string) $key,
            $this->options['token_field'] => $this->getToken($key),
        );

        try {
            $result = $this->getCollection()->deleteOne($filter);
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            throw new LockConflictedException('Failed to delete lock', 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     *
     * db.lock.find({
     *     _id: "test",
     *     expires_at: {
     *         $gte : new Date()
     *     }
     * });
     */
    public function exists(Key $key)
    {
        $filter = array(
            $this->options['resource_field'] => (string) $key,
            $this->options['expiry_field'] => array(
                '$gte' => $this->createDateTime(),
            ),
        );

        $doc = $this->getCollection()->findOne($filter);

        return $doc && $doc['token'] === $this->getToken($key);
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
