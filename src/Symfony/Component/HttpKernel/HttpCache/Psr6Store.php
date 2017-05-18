<?php

namespace Symfony\Component\HttpKernel\HttpCache;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class Psr6Store implements StoreInterface
{
    /**
     * @var CacheItemPoolInterface
     */
    private $cachePool;

    /**
     * List of locks acquired by the current process.
     *
     * @var array
     */
    private $locks = array();

    /**
     * @param CacheItemPoolInterface $cachePool
     */
    public function __construct(CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    /**
     * Locates a cached Response for the Request provided.
     *
     * @param Request $request A Request instance
     *
     * @return Response|null A Response instance, or null if no cache entry was found
     */
    public function lookup(Request $request)
    {
        // TODO: Implement lookup() method.
    }

    /**
     * Writes a cache entry to the store for the given Request and Response.
     *
     * Existing entries are read and any that match the response are removed. This
     * method calls write with the new list of cache entries.
     *
     * @param Request  $request  A Request instance
     * @param Response $response A Response instance
     *
     * @return string The key under which the response is stored
     */
    public function write(Request $request, Response $response)
    {
        if (!$response->headers->has('X-Content-Digest')) {
            $contentDigest = $this->generateContentDigest($response);

            if (false === $this->save($contentDigest, $response->getContent())) {
                throw new \RuntimeException('Unable to store the entity.');
            }

            $response->headers->set('X-Content-Digest', $contentDigest);

            if (!$response->headers->has('Transfer-Encoding')) {
                $response->headers->set('Content-Length', strlen($response->getContent()));
            }
        }

        $key = $this->getCacheKey($request);
        $headers = $response->headers->all();
        unset($headers['age']);

        $this->save($key, serialize(array(array($request->headers->all(), $headers))));
    }

    /**
     * Invalidates all cache entries that match the request.
     *
     * @param Request $request A Request instance
     */
    public function invalidate(Request $request)
    {
        // TODO: Implement invalidate() method.
    }

    /**
     * Locks the cache for a given Request.
     *
     * @param Request $request A Request instance
     *
     * @return bool|string true if the lock is acquired, the path to the current lock otherwise
     */
    public function lock(Request $request)
    {
        $lockKey = $this->getLockKey($request);

        if (isset($this->locks[$lockKey])) {
            return true;
        }

        $item = $this->cachePool->getItem($lockKey);

        if ($item->isHit()) {
            return false;
        }

        $this->cachePool->save($item);

        $this->locks[$lockKey] = true;

        return true;
    }

    /**
     * Releases the lock for the given Request.
     *
     * @param Request $request A Request instance
     *
     * @return bool False if the lock file does not exist or cannot be unlocked, true otherwise
     */
    public function unlock(Request $request)
    {
        $lockKey = $this->getLockKey($request);

        if (!isset($this->locks[$lockKey])) {
            return false;
        }

        $this->cachePool->deleteItem($lockKey);

        unset($this->locks[$lockKey]);

        return true;
    }

    /**
     * Returns whether or not a lock exists.
     *
     * @param Request $request A Request instance
     *
     * @return bool true if lock exists, false otherwise
     */
    public function isLocked(Request $request)
    {
        $lockKey = $this->getLockKey($request);

        if (isset($this->locks[$lockKey])) {
            return true;
        }

        return $this->cachePool->hasItem($this->getLockKey($request));
    }

    /**
     * Purges data for the given URL.
     *
     * @param string $url A URL
     *
     * @return bool true if the URL exists and has been purged, false otherwise
     */
    public function purge($url)
    {
        // TODO: Implement purge() method.
    }

    /**
     * Cleanups storage.
     */
    public function cleanup()
    {
        $this->cachePool->deleteItems(array_keys($this->locks));
        $this->locks = array();
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    private function getCacheKey(Request $request)
    {
        return 'md'.hash('sha256', $request->getUri());
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    private function getLockKey(Request $request)
    {
        return $this->getCacheKey($request).'.lock';
    }

    /**
     * @param Response $response
     *
     * @return string
     */
    private function generateContentDigest(Response $response)
    {
        return 'en'.hash('sha256', $response->getContent());
    }

    /**
     * @param string $key
     * @param string $data
     *
     * @return bool
     */
    private function save($key, $data)
    {
        return $this->cachePool->save($this->createCacheItem($key, $data));
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param bool   $isHit
     *
     * @return CacheItem
     */
    private function createCacheItem($key, $value, $isHit = false)
    {
        $f = \Closure::bind(
            function ($key, $value, $isHit) {
                $item = new CacheItem();
                $item->key = $key;
                $item->value = $value;
                $item->isHit = $isHit;

                return $item;
            },
            null,
            CacheItem::class
        );

        return $f($key, $value, $isHit);
    }
}
