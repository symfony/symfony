<?php

namespace Symfony\Component\HttpKernel\Tests\HttpCache;

use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\Psr6Store;

class Psr6StoreTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var TestArrayAdapter
     */
    private $cachePool;

    /**
     * @var Psr6Store
     */
    private $store;

    protected function setUp()
    {
        $this->request = Request::create('/');
        $this->response = new Response('hello world', 200, array());

        $this->cachePool = new TestArrayAdapter();
        $this->store = new Psr6Store($this->cachePool);
    }

    public function testItLocksTheRequest()
    {
        $lockKey = $this->getRequestLockKey();

        $result = $this->store->lock($this->request);

        $this->assertTrue($result, 'It returns true if lock is acquired.');
        $this->assertTrue($this->store->isLocked($this->request), 'Request is locked.');
        $this->assertTrue($this->cachePool->hasItem($lockKey), 'The cache pool stores the lock.');
    }

    public function testLockReturnsFalseIfTheLockWasAlreadyAcquiredByAnotherProcess()
    {
        $this->createLock();

        $result = $this->store->lock($this->request);

        $this->assertFalse($result, 'It returns false if lock could not be acquired.');
        $this->assertTrue($this->store->isLocked($this->request), 'Request is locked.');
        $this->assertTrue($this->cachePool->hasItem($this->getRequestLockKey()), 'The cache pool stores the lock.');
    }

    public function testLockReturnsTrueIfTheLockWasAlreadyAcquiredByTheSameProcess()
    {
        $this->store->lock($this->request);
        $result = $this->store->lock($this->request);

        $this->assertTrue($result, 'It returns true if lock was already acquired by the same process.');
        $this->assertTrue($this->store->isLocked($this->request), 'Request is locked.');
        $this->assertTrue($this->cachePool->hasItem($this->getRequestLockKey()), 'The cache pool stores the lock.');
    }

    public function testIsLockedReturnsFalseIfRequestIsNotLocked()
    {
        $this->assertFalse($this->store->isLocked($this->request), 'Request is not locked.');
    }

    public function testIsLockedReturnsTrueIfLockWasAcquiredByTheCurrentProcess()
    {
        $this->store->lock($this->request);

        $this->assertTrue($this->store->isLocked($this->request), 'Request is locked.');
    }

    public function testIsLockedReturnsTrueIfLockWasAcquiredByAnotherProcess()
    {
        $this->createLock();

        $this->assertTrue($this->store->isLocked($this->request), 'Request is locked.');
    }

    public function testUnlockReturnsFalseIfLockWasNotAquiredByTheCurrentProcess()
    {
        $this->createLock();

        $this->assertFalse($this->store->unlock($this->request), 'Request is not locked.');
    }

    public function testUnlockReturnsTrueIfLockIsReleased()
    {
        $this->store->lock($this->request);

        $this->assertTrue($this->store->unlock($this->request), 'Request was unlocked.');
        $this->assertFalse($this->store->isLocked($this->request), 'Request is not locked.');
    }

    public function testLocksAreReleasedOnCleanup()
    {
        $this->store->lock($this->request);

        $this->store->cleanup();

        $this->assertFalse($this->store->isLocked($this->request), 'Request is no longer locked.');
        $this->assertFalse($this->cachePool->hasItem($this->getRequestLockKey()), 'Lock is no longer stored.');
    }

    public function testWriteStoresTheResponseContent()
    {
        $this->store->write($this->request, $this->response);

        $this->assertTrue($this->cachePool->hasItem($this->getContentDigest()), 'Response content is stored in cache.');
        $this->assertSame($this->response->getContent(), $this->cachePool->getItem($this->getContentDigest())->get(), 'Response content is stored in cache.');
        $this->assertSame($this->getContentDigest(), $this->response->headers->get('X-Content-Digest'), 'Content digest is stored in the response header.');
        $this->assertSame(strlen($this->response->getContent()), $this->response->headers->get('Content-Length'), 'Response content length is updated.');
    }

    public function testWriteDoesNotStoreTheResponseContentOfNonOriginalResponse()
    {
        $this->response->headers->set('X-Content-Digest', $this->getContentDigest());

        $this->store->write($this->request, $this->response);

        $this->assertFalse($this->cachePool->hasItem($this->getContentDigest()), 'Response content is not stored in cache.');
        $this->assertFalse($this->response->headers->has('Content-Length'), 'Response content length is not updated.');
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testWriteThrowsAnExceptionIfResponseContentCouldNotBeStoredInCache()
    {
        $this->cachePool->willFailOnSave($this->getContentDigest());

        $this->store->write($this->request, $this->response);
    }

    public function testWriteOnlyUpdatesContentLengthIfThereIsNoTransferEncodingHeader()
    {
        $this->response->headers->set('Transfer-Encoding', 'chunked');

        $this->store->write($this->request, $this->response);

        $this->assertFalse($this->response->headers->has('Content-Length'), 'Response content length is not updated.');
    }

    public function testWriteStoresEntryMetadata()
    {
        $this->response->headers->set('age', 120);

        $this->store->write($this->request, $this->response);

        $cacheItem = $this->cachePool->getItem($this->getRequestCacheKey());

        $this->assertInstanceOf('Psr\Cache\CacheItemInterface', $cacheItem, 'Metadata is stored in cache.');
        $this->assertTrue($cacheItem->isHit(), 'Metadata is stored in cache.');

        $metadata = unserialize($cacheItem->get());
        $this->assertInternalType('array', $metadata, 'Metadata is stored in cache.');
        $this->assertCount(1, $metadata, 'One entry is stored as metadata.');
        $this->assertCount(2, $metadata[0], 'Request and response headers are stored.');
        $this->assertSame($metadata[0][0], $this->request->headers->all(), 'Request headers are stored as metadata.');
        $this->assertSame($metadata[0][1], array_diff_key($this->response->headers->all(), array('age' => array())), 'Response headers are stored as metadata with no age header.');
    }

    /**
     * @return string
     */
    private function getRequestCacheKey()
    {
        return 'md'.hash('sha256', $this->request->getUri());
    }

    /**
     * @return string
     */
    private function getRequestLockKey()
    {
        return $this->getRequestCacheKey().'.lock';
    }

    /**
     * @return string
     */
    private function getContentDigest()
    {
        return 'en'.hash('sha256', $this->response->getContent());
    }

    private function createLock()
    {
        $this->cachePool->save($this->cachePool->getItem($this->getRequestLockKey()));
    }
}

class TestArrayAdapter extends ArrayAdapter
{
    private $failOnSave = array();

    public function willFailOnSave($key)
    {
        $this->failOnSave[] = $key;
    }

    public function save(CacheItemInterface $item)
    {
        if (in_array($item->getKey(), $this->failOnSave)) {
            return false;
        }

        return parent::save($item);
    }
}
