<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Adapter;

use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Cache\Traits\AbstractTrait;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractAdapter implements AdapterInterface, LoggerAwareInterface, ResettableInterface
{
    use AbstractTrait;

    private static $apcuSupported;
    private static $phpFilesSupported;

    private $createCacheItem;
    private $mergeByLifetime;

    protected function __construct(string $namespace = '', int $defaultLifetime = 0)
    {
        $this->namespace = '' === $namespace ? '' : CacheItem::validateKey($namespace).':';
        if (null !== $this->maxIdLength && \strlen($namespace) > $this->maxIdLength - 24) {
            throw new InvalidArgumentException(sprintf('Namespace must be %d chars max, %d given ("%s")', $this->maxIdLength - 24, \strlen($namespace), $namespace));
        }
        $this->createCacheItem = \Closure::bind(
            function ($key, $value, $isHit) use ($defaultLifetime) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;
                $item->defaultLifetime = $defaultLifetime;

                return $item;
            },
            null,
            CacheItem::class
        );
        $getId = \Closure::fromCallable(array($this, 'getId'));
        $this->mergeByLifetime = \Closure::bind(
            function ($deferred, $namespace, &$expiredIds) use ($getId) {
                $byLifetime = array();
                $now = time();
                $expiredIds = array();

                foreach ($deferred as $key => $item) {
                    $key = (string) $key;
                    if (null === $item->expiry) {
                        $byLifetime[0 < $item->defaultLifetime ? $item->defaultLifetime : 0][$getId($key)] = $item->value;
                    } elseif ($item->expiry > $now) {
                        $byLifetime[$item->expiry - $now][$getId($key)] = $item->value;
                    } else {
                        $expiredIds[] = $getId($key);
                    }
                }

                return $byLifetime;
            },
            null,
            CacheItem::class
        );
    }

    /**
     * @param string               $namespace
     * @param int                  $defaultLifetime
     * @param string               $version
     * @param string               $directory
     * @param LoggerInterface|null $logger
     *
     * @return AdapterInterface
     */
    public static function createSystemCache($namespace, $defaultLifetime, $version, $directory, LoggerInterface $logger = null)
    {
        if (null === self::$apcuSupported) {
            self::$apcuSupported = ApcuAdapter::isSupported();
        }

        if (!self::$apcuSupported && null === self::$phpFilesSupported) {
            self::$phpFilesSupported = PhpFilesAdapter::isSupported();
        }

        if (self::$phpFilesSupported) {
            $opcache = new PhpFilesAdapter($namespace, $defaultLifetime, $directory);
            if (null !== $logger) {
                $opcache->setLogger($logger);
            }

            return $opcache;
        }

        $fs = new FilesystemAdapter($namespace, $defaultLifetime, $directory);
        if (null !== $logger) {
            $fs->setLogger($logger);
        }
        if (!self::$apcuSupported) {
            return $fs;
        }

        $apcu = new ApcuAdapter($namespace, (int) $defaultLifetime / 5, $version);
        if ('cli' === \PHP_SAPI && !filter_var(ini_get('apc.enable_cli'), FILTER_VALIDATE_BOOLEAN)) {
            $apcu->setLogger(new NullLogger());
        } elseif (null !== $logger) {
            $apcu->setLogger($logger);
        }

        return new ChainAdapter(array($apcu, $fs));
    }

    public static function createConnection($dsn, array $options = array())
    {
        if (!\is_string($dsn)) {
            throw new InvalidArgumentException(sprintf('The %s() method expect argument #1 to be string, %s given.', __METHOD__, \gettype($dsn)));
        }
        if (0 === strpos($dsn, 'redis://')) {
            return RedisAdapter::createConnection($dsn, $options);
        }
        if (0 === strpos($dsn, 'memcached://')) {
            return MemcachedAdapter::createConnection($dsn, $options);
        }

        throw new InvalidArgumentException(sprintf('Unsupported DSN: %s.', $dsn));
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        if ($this->deferred) {
            $this->commit();
        }
        $id = $this->getId($key);

        $f = $this->createCacheItem;
        $isHit = false;
        $value = null;

        try {
            foreach ($this->doFetch(array($id)) as $value) {
                $isHit = true;
            }
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to fetch key "{key}"', array('key' => $key, 'exception' => $e));
        }

        return $f($key, $value, $isHit);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = array())
    {
        if ($this->deferred) {
            $this->commit();
        }
        $ids = array();

        foreach ($keys as $key) {
            $ids[] = $this->getId($key);
        }
        try {
            $items = $this->doFetch($ids);
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to fetch requested items', array('keys' => $keys, 'exception' => $e));
            $items = array();
        }
        $ids = array_combine($ids, $keys);

        return $this->generateItems($items, $ids);
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        if (!$item instanceof CacheItem) {
            return false;
        }
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $ok = true;
        $byLifetime = $this->mergeByLifetime;
        $byLifetime = $byLifetime($this->deferred, $this->namespace, $expiredIds);
        $retry = $this->deferred = array();

        if ($expiredIds) {
            $this->doDelete($expiredIds);
        }
        foreach ($byLifetime as $lifetime => $values) {
            try {
                $e = $this->doSave($values, $lifetime);
            } catch (\Exception $e) {
            }
            if (true === $e || array() === $e) {
                continue;
            }
            if (\is_array($e) || 1 === \count($values)) {
                foreach (\is_array($e) ? $e : array_keys($values) as $id) {
                    $ok = false;
                    $v = $values[$id];
                    $type = \is_object($v) ? \get_class($v) : \gettype($v);
                    CacheItem::log($this->logger, 'Failed to save key "{key}" ({type})', array('key' => substr($id, \strlen($this->namespace)), 'type' => $type, 'exception' => $e instanceof \Exception ? $e : null));
                }
            } else {
                foreach ($values as $id => $v) {
                    $retry[$lifetime][] = $id;
                }
            }
        }

        // When bulk-save failed, retry each item individually
        foreach ($retry as $lifetime => $ids) {
            foreach ($ids as $id) {
                try {
                    $v = $byLifetime[$lifetime][$id];
                    $e = $this->doSave(array($id => $v), $lifetime);
                } catch (\Exception $e) {
                }
                if (true === $e || array() === $e) {
                    continue;
                }
                $ok = false;
                $type = \is_object($v) ? \get_class($v) : \gettype($v);
                CacheItem::log($this->logger, 'Failed to save key "{key}" ({type})', array('key' => substr($id, \strlen($this->namespace)), 'type' => $type, 'exception' => $e instanceof \Exception ? $e : null));
            }
        }

        return $ok;
    }

    public function __destruct()
    {
        if ($this->deferred) {
            $this->commit();
        }
    }

    private function generateItems($items, &$keys)
    {
        $f = $this->createCacheItem;

        try {
            foreach ($items as $id => $value) {
                if (!isset($keys[$id])) {
                    $id = key($keys);
                }
                $key = $keys[$id];
                unset($keys[$id]);
                yield $key => $f($key, $value, true);
            }
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to fetch requested items', array('keys' => array_values($keys), 'exception' => $e));
        }

        foreach ($keys as $key) {
            yield $key => $f($key, null, false);
        }
    }
}
