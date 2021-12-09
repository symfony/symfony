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
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockConflictedException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\PersistingStoreInterface;

/**
 * ZookeeperStore is a PersistingStoreInterface implementation using Zookeeper as store engine.
 *
 * @author Ganesh Chandrasekaran <gchandrasekaran@wayfair.com>
 */
class ZookeeperStore implements PersistingStoreInterface
{
    use ExpiringStoreTrait;

    private $zookeeper;

    public function __construct(\Zookeeper $zookeeper)
    {
        $this->zookeeper = $zookeeper;
    }

    public static function createConnection(string $dsn): \Zookeeper
    {
        if (!str_starts_with($dsn, 'zookeeper:')) {
            throw new InvalidArgumentException(sprintf('Unsupported DSN: "%s".', $dsn));
        }

        if (false === $params = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('Invalid Zookeeper DSN: "%s".', $dsn));
        }

        $host = $params['host'] ?? '';
        if (isset($params['port'])) {
            $host .= ':'.$params['port'];
        }

        return new \Zookeeper($host);
    }

    /**
     * {@inheritdoc}
     */
    public function save(Key $key)
    {
        if ($this->exists($key)) {
            return;
        }

        $resource = $this->getKeyResource($key);
        $token = $this->getUniqueToken($key);

        $this->createNewLock($resource, $token);
        $key->markUnserializable();

        $this->checkNotExpired($key);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Key $key)
    {
        if (!$this->exists($key)) {
            return;
        }
        $resource = $this->getKeyResource($key);
        try {
            $this->zookeeper->delete($resource);
        } catch (\ZookeeperException $exception) {
            // For Zookeeper Ephemeral Nodes, the node will be deleted upon session death. But, if we want to unlock
            // the lock before proceeding further in the session, the client should be aware of this
            throw new LockReleasingException($exception);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists(Key $key): bool
    {
        $resource = $this->getKeyResource($key);
        try {
            return $this->zookeeper->get($resource) === $this->getUniqueToken($key);
        } catch (\ZookeeperException $ex) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function putOffExpiration(Key $key, float $ttl)
    {
        // do nothing, zookeeper locks forever.
    }

    /**
     * Creates a zookeeper node.
     *
     * @param string $node  The node which needs to be created
     * @param string $value The value to be assigned to a zookeeper node
     *
     * @throws LockConflictedException
     * @throws LockAcquiringException
     */
    private function createNewLock(string $node, string $value)
    {
        // Default Node Permissions
        $acl = [['perms' => \Zookeeper::PERM_ALL, 'scheme' => 'world', 'id' => 'anyone']];
        // This ensures that the nodes are deleted when the client session to zookeeper server ends.
        $type = \Zookeeper::EPHEMERAL;

        try {
            $this->zookeeper->create($node, $value, $acl, $type);
        } catch (\ZookeeperException $ex) {
            if (\Zookeeper::NODEEXISTS === $ex->getCode()) {
                throw new LockConflictedException($ex);
            }

            throw new LockAcquiringException($ex);
        }
    }

    private function getKeyResource(Key $key): string
    {
        // Since we do not support storing locks as multi-level nodes, we convert them to be stored at root level.
        // For example: foo/bar will become /foo-bar and /foo/bar will become /-foo-bar
        $resource = (string) $key;

        if (str_contains($resource, '/')) {
            $resource = strtr($resource, ['/' => '-']).'-'.sha1($resource);
        }

        if ('' === $resource) {
            $resource = sha1($resource);
        }

        return '/'.$resource;
    }

    private function getUniqueToken(Key $key): string
    {
        if (!$key->hasState(self::class)) {
            $token = base64_encode(random_bytes(32));
            $key->setState(self::class, $token);
        }

        return $key->getState(self::class);
    }
}
