<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\CacheItem;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait AbstractTrait
{
    use LoggerAwareTrait;

    private $namespace;
    private $namespaceVersion = '';
    private $versioningIsEnabled = false;
    private $deferred = array();
    private $ids = array();

    /**
     * @var int|null The maximum length to enforce for identifiers or null when no limit applies
     */
    protected $maxIdLength;

    /**
     * Fetches several cache items.
     *
     * @param array $ids The cache identifiers to fetch
     *
     * @return array|\Traversable The corresponding values found in the cache
     */
    abstract protected function doFetch(array $ids);

    /**
     * Confirms if the cache contains specified cache item.
     *
     * @param string $id The identifier for which to check existence
     *
     * @return bool True if item exists in the cache, false otherwise
     */
    abstract protected function doHave($id);

    /**
     * Deletes all items in the pool.
     *
     * @param string $namespace The prefix used for all identifiers managed by this pool
     *
     * @return bool True if the pool was successfully cleared, false otherwise
     */
    abstract protected function doClear($namespace);

    /**
     * Removes multiple items from the pool.
     *
     * @param array $ids An array of identifiers that should be removed from the pool
     *
     * @return bool True if the items were successfully removed, false otherwise
     */
    abstract protected function doDelete(array $ids);

    /**
     * Persists several cache items immediately.
     *
     * @param array $values   The values to cache, indexed by their cache identifier
     * @param int   $lifetime The lifetime of the cached values, 0 for persisting until manual cleaning
     *
     * @return array|bool The identifiers that failed to be cached or a boolean stating if caching succeeded or not
     */
    abstract protected function doSave(array $values, $lifetime);

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        $id = $this->getId($key);

        if (isset($this->deferred[$key])) {
            $this->commit();
        }

        try {
            return $this->doHave($id);
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to check if key "{key}" is cached', array('key' => $key, 'exception' => $e));

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->deferred = array();
        if ($cleared = $this->versioningIsEnabled) {
            $namespaceVersion = substr_replace(base64_encode(pack('V', mt_rand())), ':', 5);
            try {
                $cleared = $this->doSave(array('/'.$this->namespace => $namespaceVersion), 0);
            } catch (\Exception $e) {
                $cleared = false;
            }
            if ($cleared = true === $cleared || array() === $cleared) {
                $this->namespaceVersion = $namespaceVersion;
                $this->ids = array();
            }
        }

        try {
            return $this->doClear($this->namespace) || $cleared;
        } catch (\Exception $e) {
            CacheItem::log($this->logger, 'Failed to clear the cache', array('exception' => $e));

            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        return $this->deleteItems(array($key));
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $ids = array();

        foreach ($keys as $key) {
            $ids[$key] = $this->getId($key);
            unset($this->deferred[$key]);
        }

        try {
            if ($this->doDelete($ids)) {
                return true;
            }
        } catch (\Exception $e) {
        }

        $ok = true;

        // When bulk-delete failed, retry each item individually
        foreach ($ids as $key => $id) {
            try {
                $e = null;
                if ($this->doDelete(array($id))) {
                    continue;
                }
            } catch (\Exception $e) {
            }
            CacheItem::log($this->logger, 'Failed to delete key "{key}"', array('key' => $key, 'exception' => $e));
            $ok = false;
        }

        return $ok;
    }

    /**
     * Enables/disables versioning of items.
     *
     * When versioning is enabled, clearing the cache is atomic and doesn't require listing existing keys to proceed,
     * but old keys may need garbage collection and extra round-trips to the back-end are required.
     *
     * Calling this method also clears the memoized namespace version and thus forces a resynchonization of it.
     *
     * @param bool $enable
     *
     * @return bool the previous state of versioning
     */
    public function enableVersioning($enable = true)
    {
        $wasEnabled = $this->versioningIsEnabled;
        $this->versioningIsEnabled = (bool) $enable;
        $this->namespaceVersion = '';
        $this->ids = array();

        return $wasEnabled;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        if ($this->deferred) {
            $this->commit();
        }
        $this->namespaceVersion = '';
        $this->ids = array();
    }

    /**
     * Like the native unserialize() function but throws an exception if anything goes wrong.
     *
     * @param string $value
     *
     * @return mixed
     *
     * @throws \Exception
     *
     * @deprecated since Symfony 4.2, use DefaultMarshaller instead.
     */
    protected static function unserialize($value)
    {
        @trigger_error(sprintf('The "%s::unserialize()" method is deprecated since Symfony 4.2, use DefaultMarshaller instead.', __CLASS__), E_USER_DEPRECATED);

        if ('b:0;' === $value) {
            return false;
        }
        $unserializeCallbackHandler = ini_set('unserialize_callback_func', __CLASS__.'::handleUnserializeCallback');
        try {
            if (false !== $value = unserialize($value)) {
                return $value;
            }
            throw new \DomainException('Failed to unserialize cached value');
        } catch (\Error $e) {
            throw new \ErrorException($e->getMessage(), $e->getCode(), E_ERROR, $e->getFile(), $e->getLine());
        } finally {
            ini_set('unserialize_callback_func', $unserializeCallbackHandler);
        }
    }

    private function getId($key)
    {
        if ($this->versioningIsEnabled && '' === $this->namespaceVersion) {
            $this->ids = array();
            $this->namespaceVersion = '1/';
            try {
                foreach ($this->doFetch(array('/'.$this->namespace)) as $v) {
                    $this->namespaceVersion = $v;
                }
                if ('1:' === $this->namespaceVersion) {
                    $this->namespaceVersion = substr_replace(base64_encode(pack('V', time())), ':', 5);
                    $this->doSave(array('@'.$this->namespace => $this->namespaceVersion), 0);
                }
            } catch (\Exception $e) {
            }
        }

        if (\is_string($key) && isset($this->ids[$key])) {
            return $this->namespace.$this->namespaceVersion.$this->ids[$key];
        }
        CacheItem::validateKey($key);
        $this->ids[$key] = $key;

        if (null === $this->maxIdLength) {
            return $this->namespace.$this->namespaceVersion.$key;
        }
        if (\strlen($id = $this->namespace.$this->namespaceVersion.$key) > $this->maxIdLength) {
            // Use MD5 to favor speed over security, which is not an issue here
            $this->ids[$key] = $id = substr_replace(base64_encode(hash('md5', $key, true)), ':', -(\strlen($this->namespaceVersion) + 2));
            $id = $this->namespace.$this->namespaceVersion.$id;
        }

        return $id;
    }

    /**
     * @internal
     */
    public static function handleUnserializeCallback($class)
    {
        throw new \DomainException('Class not found: '.$class);
    }
}
