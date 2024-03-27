<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This code is partially based on the Rack-Cache library by Ryan Tomayko,
 * which is released under the MIT license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\HttpCache;

use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\Psr6Store;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class Psr6StoreTest extends TestCase
{
    /**
     * @var Psr6Store
     */
    private $store;

    protected function setUp(): void
    {
        $this->store = new Psr6Store(['cache_directory' => sys_get_temp_dir()]);
    }

    protected function tearDown(): void
    {
        $this->getCache()->clear();
        $this->store->cleanup();
    }

    public function testCustomCacheWithoutLockFactory(): void
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The cache_directory option is required unless you set the lock_factory explicitly as by default locks are also stored in the configured cache_directory.');

        $cache = $this->createMock(TagAwareAdapterInterface::class);

        new Psr6Store([
            'cache' => $cache,
        ]);
    }

    public function testCustomCacheAndLockFactory(): void
    {
        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->once())
            ->method('deleteItem')
            ->willReturn(true);
        $lockFactory = $this->createMock(LockFactory::class);

        $store = new Psr6Store([
            'cache' => $cache,
            'lock_factory' => $lockFactory,
        ]);

        $store->purge('/');
    }

    public function testItLocksTheRequest(): void
    {
        $request = Request::create('/');
        $result = $this->store->lock($request);

        $this->assertTrue($result, 'It returns true if lock is acquired.');
        $this->assertTrue($this->store->isLocked($request), 'Request is locked.');
    }

    public function testLockReturnsFalseIfTheLockWasAlreadyAcquired(): void
    {
        $request = Request::create('/');
        $this->store->lock($request);

        $result = $this->store->lock($request);

        $this->assertFalse($result, 'It returns false if lock could not be acquired.');
        $this->assertTrue($this->store->isLocked($request), 'Request is locked.');
    }

    public function testIsLockedReturnsFalseIfRequestIsNotLocked(): void
    {
        $request = Request::create('/');
        $this->assertFalse($this->store->isLocked($request), 'Request is not locked.');
    }

    public function testIsLockedReturnsTrueIfLockWasAcquired(): void
    {
        $request = Request::create('/');
        $this->store->lock($request);

        $this->assertTrue($this->store->isLocked($request), 'Request is locked.');
    }

    public function testUnlockReturnsFalseIfLockWasNotAcquired(): void
    {
        $request = Request::create('/');
        $this->assertFalse($this->store->unlock($request), 'Request is not locked.');
    }

    public function testUnlockReturnsTrueIfLockIsReleased(): void
    {
        $request = Request::create('/');
        $this->store->lock($request);

        $this->assertTrue($this->store->unlock($request), 'Request was unlocked.');
        $this->assertFalse($this->store->isLocked($request), 'Request is not locked.');
    }

    public function testLocksAreReleasedOnCleanup(): void
    {
        $request = Request::create('/');
        $this->store->lock($request);

        $this->store->cleanup();

        $this->assertFalse($this->store->isLocked($request), 'Request is no longer locked.');
    }

    public function testSameLockCanBeAcquiredAgain(): void
    {
        $request = Request::create('/');

        $this->assertTrue($this->store->lock($request));
        $this->assertTrue($this->store->unlock($request));
        $this->assertTrue($this->store->lock($request));
    }

    public function testThrowsIfResponseHasNoExpirationTime(): void
    {
        $request = Request::create('/');
        $response = new Response('hello world', 200);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('HttpCache should not forward any response without any cache expiration time to the store.');
        $this->store->write($request, $response);
    }

    public function testWriteThrowsExceptionIfDigestCannotBeStored(): void
    {
        $innerCache = new ArrayAdapter();
        $cache = $this->getMockBuilder(TagAwareAdapter::class)
            ->setConstructorArgs([$innerCache])
            ->onlyMethods(['saveDeferred'])
            ->getMock();

        $cache
            ->expects($this->once())
            ->method('saveDeferred')
            ->willReturn(false);

        $store = new Psr6Store([
            'cache_directory' => sys_get_temp_dir(),
            'cache' => $cache,
        ]);

        $request = Request::create('/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to store the entity.');
        $store->write($request, $response);
    }

    public function testWriteStoresTheResponseContent(): void
    {
        $request = Request::create('/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

        $contentDigest = $this->store->generateContentDigest($response);

        $this->store->write($request, $response);

        $this->assertTrue($this->getCache()->hasItem($contentDigest), 'Response content is stored in cache.');
        $this->assertSame(['expires' => 600, 'contents' => $response->getContent()], $this->getCache()->getItem($contentDigest)->get(), 'Response content is stored in cache.');
        $this->assertSame($contentDigest, $response->headers->get('X-Content-Digest'), 'Content digest is stored in the response header.');
        $this->assertSame(\strlen($response->getContent()), (int)$response->headers->get('Content-Length'), 'Response content length is updated.');
    }

    public function testWriteDoesNotStoreTheResponseContentOfNonOriginalResponse(): void
    {
        $request = Request::create('/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

        $contentDigest = $this->store->generateContentDigest($response);

        $response->headers->set('X-Content-Digest', $contentDigest);

        $this->store->write($request, $response);

        $this->assertFalse($this->getCache()->hasItem($contentDigest), 'Response content is not stored in cache.');
        $this->assertFalse($response->headers->has('Content-Length'), 'Response content length is not updated.');
    }

    public function testWriteOnlyUpdatesContentLengthIfThereIsNoTransferEncodingHeader(): void
    {
        $request = Request::create('/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Transfer-Encoding', 'chunked');

        $this->store->write($request, $response);

        $this->assertFalse($response->headers->has('Content-Length'), 'Response content length is not updated.');
    }

    public function testWriteStoresEntries(): void
    {
        $request = Request::create('/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('age', 120);

        $cacheKey = $this->store->getCacheKey($request);

        $this->store->write($request, $response);

        $cacheItem = $this->getCache()->getItem($cacheKey);

        $this->assertInstanceOf(CacheItemInterface::class, $cacheItem, 'Metadata is stored in cache.');
        $this->assertTrue($cacheItem->isHit(), 'Metadata is stored in cache.');

        $entries = $cacheItem->get();

        $this->assertTrue(\is_array($entries), 'Entries are stored in cache.');
        $this->assertCount(1, $entries, 'One entry is stored.');
        $this->assertSame($entries[Psr6Store::NON_VARYING_KEY]['headers'], array_diff_key($response->headers->all(), ['age' => []]), 'Response headers are stored with no age header.');
    }

    public function testWriteAddsTags(): void
    {
        $request = Request::create('/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Cache-Tags', 'foobar,other tag');

        $cacheKey = $this->store->getCacheKey($request);

        $this->store->write($request, $response);

        $this->assertTrue($this->getCache()->getItem($cacheKey)->isHit());
        $this->assertTrue($this->store->invalidateTags(['foobar']));
        $this->assertFalse($this->getCache()->getItem($cacheKey)->isHit());
    }

    public function testWriteAddsTagsWithMultipleHeaders(): void
    {
        $request = Request::create('/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Cache-Tags', ['foobar,other tag', 'some,more', 'tags', 'split,over', 'multiple-headers']);

        $cacheKey = $this->store->getCacheKey($request);

        $this->store->write($request, $response);

        $this->assertTrue($this->getCache()->getItem($cacheKey)->isHit());
        $this->assertTrue($this->store->invalidateTags(['multiple-headers']));
        $this->assertFalse($this->getCache()->getItem($cacheKey)->isHit());
    }

    public function testInvalidateTagsThrowsExceptionIfWrongCacheAdapterProvided(): void
    {
        $this->expectException(\RuntimeException::class);
        $store = new Psr6Store([
            'cache' => $this->createMock(AdapterInterface::class),
            'cache_directory' => 'foobar',
        ]);
        $store->invalidateTags(['foobar']);
    }

    public function testInvalidateTagsReturnsFalseOnException(): void
    {
        $innerCache = new ArrayAdapter();
        $cache = $this->getMockBuilder(TagAwareAdapter::class)
            ->setConstructorArgs([$innerCache])
            ->setMethods(['invalidateTags'])
            ->getMock();

        $cache
            ->expects($this->once())
            ->method('invalidateTags')
            ->willThrowException(new \Symfony\Component\Cache\Exception\InvalidArgumentException());

        $store = new Psr6Store([
            'cache_directory' => sys_get_temp_dir(),
            'cache' => $cache,
        ]);

        $this->assertFalse($store->invalidateTags(['foobar']));
    }

    public function testVaryResponseDropsNonVaryingOne(): void
    {
        $request = Request::create('/');
        $nonVarying = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);
        $varying = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public', 'Vary' => 'Foobar', 'Foobar' => 'whatever']);

        $this->store->write($request, $nonVarying);

        $cacheKey = $this->store->getCacheKey($request);
        $cacheItem = $this->getCache()->getItem($cacheKey);
        $entries = $cacheItem->get();

        $this->assertCount(1, $entries);
        $this->assertSame(Psr6Store::NON_VARYING_KEY, key($entries));

        $this->store->write($request, $varying);

        $cacheItem = $this->getCache()->getItem($cacheKey);

        $entries = $cacheItem->get();

        $this->assertCount(1, $entries);
        $this->assertNotSame(Psr6Store::NON_VARYING_KEY, key($entries));
    }

    public function testRegularCacheKey(): void
    {
        $request = Request::create('https://foobar.com/');
        $expected = 'md' . hash('sha256', 'foobar.com/');
        $this->assertSame($expected, $this->store->getCacheKey($request));
    }

    public function testHttpAndHttpsGenerateTheSameCacheKey(): void
    {
        $request = Request::create('https://foobar.com/');
        $cacheKeyHttps = $this->store->getCacheKey($request);
        $request = Request::create('http://foobar.com/');
        $cacheKeyHttp = $this->store->getCacheKey($request);

        $this->assertSame($cacheKeyHttps, $cacheKeyHttp);
    }

    public function testDebugInfoIsAdded(): void
    {
        $request = Request::create('https://foobar.com/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

        $this->store->write($request, $response);

        $cacheKey = $this->store->getCacheKey($request);
        $cacheItem = $this->getCache()->getItem($cacheKey);
        $entries = $cacheItem->get();
        $this->assertSame('https://foobar.com/', $entries[Psr6Store::NON_VARYING_KEY]['uri']);
    }

    public function testRegularLookup(): void
    {
        $request = Request::create('https://foobar.com/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Foobar', 'whatever');

        $this->store->write($request, $response);

        $result = $this->store->lookup($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('hello world', $result->getContent());
        $this->assertSame('whatever', $result->headers->get('Foobar'));

        $this->assertSame('enb94d27b9934d3e08a52e52d7da7dabfac484efe37a5380ee9088f7ace2efcde9', $result->headers->get('X-Content-Digest'));
    }

    public function testRegularLookupWithContentDigestsDisabled(): void
    {
        $request = Request::create('https://foobar.com/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Foobar', 'whatever');

        $store = new Psr6Store([
            'cache_directory' => sys_get_temp_dir(),
            'generate_content_digests' => false,
        ]);

        $store->write($request, $response);

        $result = $store->lookup($request);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('hello world', $result->getContent());
        $this->assertSame('whatever', $result->headers->get('Foobar'));
        $this->assertNull($result->headers->get('X-Content-Digest'));
    }

    public function testRegularLookupWithBinaryResponse(): void
    {
        $request = Request::create('https://foobar.com/');
        $response = new BinaryFileResponse(__DIR__ . '/../Fixtures/favicon.ico', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Foobar', 'whatever');

        $this->store->write($request, $response);

        $result = $this->store->lookup($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(__DIR__ . '/../Fixtures/favicon.ico', $result->getFile()->getPathname());
        $this->assertSame('whatever', $result->headers->get('Foobar'));
        $this->assertSame('bfe8149cee23ba25e6b878864c1c8b3344ee1b3d5c6d468b2e4f7593be65bb1b68', $result->headers->get('X-Content-Digest'));
    }

    public function testRegularLookupWithBinaryResponseWithContentDigestsDisabled(): void
    {
        $request = Request::create('https://foobar.com/');
        $response = new BinaryFileResponse(__DIR__ . '/../Fixtures/favicon.ico', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Foobar', 'whatever');

        $store = new Psr6Store([
            'cache_directory' => sys_get_temp_dir(),
            'generate_content_digests' => false,
        ]);

        $store->write($request, $response);

        $result = $store->lookup($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $result);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame(__DIR__ . '/../Fixtures/favicon.ico', $result->getFile()->getPathname());
        $this->assertSame('whatever', $result->headers->get('Foobar'));
        $this->assertSame('bfe8149cee23ba25e6b878864c1c8b3344ee1b3d5c6d468b2e4f7593be65bb1b68', $result->headers->get('X-Content-Digest'));
    }

    public function testRegularLookupWithRemovedBinaryResponse(): void
    {
        $request = Request::create('https://foobar.com/');
        $file = new File(__DIR__ . '/../Fixtures/favicon.ico');
        $response = new BinaryFileResponse($file, 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Foobar', 'whatever');

        $this->store->write($request, $response);

        // Now move (same as remove) the file somewhere else
        $movedFile = $file->move(__DIR__ . '/../Fixtures', 'favicon_bu.ico');

        $result = $this->store->lookup($request);
        $this->assertNull($result);

        // Move back for other tests
        $movedFile->move(__DIR__ . '/Fixtures', 'favicon.ico');
    }

    public function testLookupWithVaryOnCookies(): void
    {
        // Cookies match
        $request = Request::create('https://foobar.com/', 'GET', [], ['Foo' => 'Bar'], [], ['HTTP_COOKIE' => 'Foo=Bar']);
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public', 'Vary' => 'Cookie']);
        $response->headers->setCookie(new Cookie('Foo', 'Bar', 0, '/'));

        $this->store->write($request, $response);

        $result = $this->store->lookup($request);
        $this->assertInstanceOf(Response::class, $result);

        // Cookies do not match (manually removed on request)
        $request = Request::create('https://foobar.com/', 'GET', [], ['Foo' => 'Bar'], [], ['HTTP_COOKIE' => 'Foo=Bar']);
        $request->cookies->remove('Foo');

        $result = $this->store->lookup($request);
        $this->assertNull($result);
    }

    public function testLookupWithEmptyCache(): void
    {
        $request = Request::create('https://foobar.com/');

        $result = $this->store->lookup($request);

        $this->assertNull($result);
    }

    public function testLookupWithVaryResponse(): void
    {
        $request = Request::create('https://foobar.com/');
        $request->headers->set('Foobar', 'whatever');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public', 'Vary' => 'Foobar']);

        $this->store->write($request, $response);

        $request = Request::create('https://foobar.com/');
        $result = $this->store->lookup($request);
        $this->assertNull($result);

        $request = Request::create('https://foobar.com/');
        $request->headers->set('Foobar', 'whatever');
        $result = $this->store->lookup($request);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('hello world', $result->getContent());
        $this->assertSame('Foobar', $result->headers->get('Vary'));
    }

    public function testLookupWithMultipleVaryResponse(): void
    {
        $jsonRequest = Request::create('https://foobar.com/');
        $jsonRequest->headers->set('Accept', 'application/json');
        $htmlRequest = Request::create('https://foobar.com/');
        $htmlRequest->headers->set('Accept', 'text/html');

        $jsonResponse = new Response('{}', 200, ['Cache-Control' => 's-maxage=600, public', 'Vary' => 'Accept', 'Content-Type' => 'application/json']);
        $htmlResponse = new Response('<html></html>', 200, ['Cache-Control' => 's-maxage=600, public', 'Vary' => 'Accept', 'Content-Type' => 'text/html']);

        // Fill cache
        $this->store->write($jsonRequest, $jsonResponse);
        $this->store->write($htmlRequest, $htmlResponse);

        // Should return null because no header provided
        $request = Request::create('https://foobar.com/');
        $result = $this->store->lookup($request);
        $this->assertNull($result);

        // Should return null because header provided but non matching content
        $request = Request::create('https://foobar.com/');
        $request->headers->set('Accept', 'application/xml');
        $result = $this->store->lookup($request);
        $this->assertNull($result);

        // Should return a JSON response
        $request = Request::create('https://foobar.com/');
        $request->headers->set('Accept', 'application/json');
        $result = $this->store->lookup($request);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('{}', $result->getContent());
        $this->assertSame('Accept', $result->headers->get('Vary'));
        $this->assertSame('application/json', $result->headers->get('Content-Type'));

        // Should return an HTML response
        $request = Request::create('https://foobar.com/');
        $request->headers->set('Accept', 'text/html');
        $result = $this->store->lookup($request);
        $this->assertSame(200, $result->getStatusCode());
        $this->assertSame('<html></html>', $result->getContent());
        $this->assertSame('Accept', $result->headers->get('Vary'));
        $this->assertSame('text/html', $result->headers->get('Content-Type'));
    }

    public function testInvalidate(): void
    {
        $request = Request::create('https://foobar.com/');
        $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);
        $response->headers->set('Foobar', 'whatever');

        $this->store->write($request, $response);
        $cacheKey = $this->store->getCacheKey($request);

        $cacheItem = $this->getCache()->getItem($cacheKey);
        $this->assertTrue($cacheItem->isHit());

        $this->store->invalidate($request);

        $cacheItem = $this->getCache()->getItem($cacheKey);
        $this->assertFalse($cacheItem->isHit());
    }

    public function testPurge(): void
    {
        // Request 1
        $request1 = Request::create('https://foobar.com/');
        $response1 = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

        // Request 2
        $request2 = Request::create('https://foobar.com/foobar');
        $response2 = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

        $this->store->write($request1, $response1);
        $this->store->write($request2, $response2);
        $cacheKey1 = $this->store->getCacheKey($request1);
        $cacheKey2 = $this->store->getCacheKey($request2);

        $cacheItem1 = $this->getCache()->getItem($cacheKey1);
        $cacheItem2 = $this->getCache()->getItem($cacheKey2);
        $this->assertTrue($cacheItem1->isHit());
        $this->assertTrue($cacheItem2->isHit());

        $this->store->purge('https://foobar.com/');

        $cacheItem1 = $this->getCache()->getItem($cacheKey1);
        $cacheItem2 = $this->getCache()->getItem($cacheKey2);
        $this->assertFalse($cacheItem1->isHit());
        $this->assertTrue($cacheItem2->isHit());
    }

    public function testClear(): void
    {
        // Request 1
        $request1 = Request::create('https://foobar.com/');
        $response1 = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

        // Request 2
        $request2 = Request::create('https://foobar.com/foobar');
        $response2 = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

        $this->store->write($request1, $response1);
        $this->store->write($request2, $response2);
        $cacheKey1 = $this->store->getCacheKey($request1);
        $cacheKey2 = $this->store->getCacheKey($request2);

        $cacheItem1 = $this->getCache()->getItem($cacheKey1);
        $cacheItem2 = $this->getCache()->getItem($cacheKey2);
        $this->assertTrue($cacheItem1->isHit());
        $this->assertTrue($cacheItem2->isHit());

        $this->store->clear();

        $cacheItem1 = $this->getCache()->getItem($cacheKey1);
        $cacheItem2 = $this->getCache()->getItem($cacheKey2);
        $this->assertFalse($cacheItem1->isHit());
        $this->assertFalse($cacheItem2->isHit());
    }

    public function testPruneIgnoredIfCacheBackendDoesNotImplementPrunableInterface(): void
    {
        $cache = $this->getMockBuilder(RedisAdapter::class)
            ->disableOriginalConstructor()
            ->addMethods(['prune'])
            ->getMock();
        $cache
            ->expects($this->never())
            ->method('prune');

        $store = new Psr6Store([
            'cache_directory' => sys_get_temp_dir(),
            'cache' => $cache,
        ]);

        $store->prune();
    }

    public function testAutoPruneExpiredEntries(): void
    {
        $innerCache = new ArrayAdapter();
        $cache = $this->getMockBuilder(TagAwareAdapter::class)
            ->setConstructorArgs([$innerCache])
            ->setMethods(['prune'])
            ->getMock();

        $cache
            ->expects($this->exactly(3))
            ->method('prune');

        $lock = $this->createMock(LockInterface::class);
        $lock
            ->expects($this->exactly(3))
            ->method('acquire')
            ->willReturn(true);
        $lock
            ->expects($this->exactly(3))
            ->method('release')
            ->willReturn(true);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->expects($this->any())
            ->method('createLock')
            ->with(Psr6Store::CLEANUP_LOCK_KEY)
            ->willReturn($lock);

        $store = new Psr6Store([
            'cache' => $cache,
            'prune_threshold' => 5,
            'lock_factory' => $lockFactory,
        ]);

        foreach (range(1, 21) as $entry) {
            $request = Request::create('https://foobar.com/' . $entry);
            $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

            $store->write($request, $response);
        }

        $store->cleanup();
    }

    public function testAutoPruneIsSkippedIfThresholdDisabled(): void
    {
        $innerCache = new ArrayAdapter();
        $cache = $this->getMockBuilder(TagAwareAdapter::class)
            ->setConstructorArgs([$innerCache])
            ->setMethods(['prune'])
            ->getMock();

        $cache
            ->expects($this->never())
            ->method('prune');

        $store = new Psr6Store([
            'cache_directory' => sys_get_temp_dir(),
            'cache' => $cache,
            'prune_threshold' => 0,
        ]);

        foreach (range(1, 21) as $entry) {
            $request = Request::create('https://foobar.com/' . $entry);
            $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

            $store->write($request, $response);
        }

        $store->cleanup();
    }

    public function testAutoPruneIsSkippedIfPruningIsAlreadyInProgress(): void
    {
        $innerCache = new ArrayAdapter();
        $cache = $this->getMockBuilder(TagAwareAdapter::class)
            ->setConstructorArgs([$innerCache])
            ->setMethods(['prune'])
            ->getMock();

        $cache
            ->expects($this->never())
            ->method('prune');

        $lock = $this->createMock(LockInterface::class);
        $lock
            ->expects($this->exactly(3))
            ->method('acquire')
            ->willReturn(false);

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->expects($this->any())
            ->method('createLock')
            ->with(Psr6Store::CLEANUP_LOCK_KEY)
            ->willReturn($lock);

        $store = new Psr6Store([
            'cache' => $cache,
            'prune_threshold' => 5,
            'lock_factory' => $lockFactory,
        ]);

        foreach (range(1, 21) as $entry) {
            $request = Request::create('https://foobar.com/' . $entry);
            $response = new Response('hello world', 200, ['Cache-Control' => 's-maxage=600, public']);

            $store->write($request, $response);
        }

        $store->cleanup();
    }

    public function testItFailsWithoutCacheDirectoryForCache(): void
    {
        $this->expectException(MissingOptionsException::class);
        new Psr6Store([]);
    }

    public function testItFailsWithoutCacheDirectoryForLockStore(): void
    {
        $this->expectException(MissingOptionsException::class);
        new Psr6Store(['cache' => $this->createMock(AdapterInterface::class)]);
    }

    public function testUnlockReturnsFalseOnLockReleasingException(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock
            ->expects($this->once())
            ->method('release')
            ->willThrowException(new LockReleasingException());

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $store = new Psr6Store([
            'cache' => $this->createMock(AdapterInterface::class),
            'lock_factory' => $lockFactory,
        ]);

        $request = Request::create('/foobar');
        $store->lock($request);

        $this->assertFalse($store->unlock($request));
    }

    public function testLockReleasingExceptionIsIgnoredOnCleanup(): void
    {
        $lock = $this->createMock(LockInterface::class);
        $lock
            ->expects($this->once())
            ->method('release')
            ->willThrowException(new LockReleasingException());

        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory
            ->expects($this->once())
            ->method('createLock')
            ->willReturn($lock);

        $store = new Psr6Store([
            'cache' => $this->createMock(AdapterInterface::class),
            'lock_factory' => $lockFactory,
        ]);

        $request = Request::create('/foobar');
        $store->lock($request);
        $store->cleanup();

        // This test will fail if an exception is thrown, otherwise we mark it
        // as passed.
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider contentDigestExpiryProvider
     */
    public function testContentDigestExpiresCorrectly(array $responseHeaders, $expectedExpiresAfter, $previousItemExpiration = 0): void
    {
        // This is the mock for the meta cache item, we're not interested in this one
        $cacheItem = $this->createMock(CacheItemInterface::class);

        // This is the one we're interested in this test
        $contentDigestCacheItem = $this->createMock(CacheItemInterface::class);
        $contentDigestCacheItem
            ->expects($this->once())
            ->method('isHit')
            ->willReturn(0 !== $previousItemExpiration);

        if (0 !== $previousItemExpiration) {
            $contentDigestCacheItem
                ->expects($this->once())
                ->method('get')
                ->willReturn(['expires' => $previousItemExpiration, 'contents' => 'foobar']);
        } else {
            $contentDigestCacheItem
                ->expects($this->once())
                ->method('set')
                ->with(['expires' => $expectedExpiresAfter, 'contents' => 'foobar']);
            $contentDigestCacheItem
                ->expects($this->once())
                ->method('expiresAfter')
                ->with($expectedExpiresAfter);
        }

        $cache = $this->createMock(AdapterInterface::class);
        $cache
            ->expects($this->exactly(3))
            ->method('getItem')
            ->withConsecutive(
                ['enc3ab8ff13720e8ad9047dd39466b3c8974e592c2fa383d4a3960714caef0c4f2'], // content digest
                ['md390aa862a7f27c16d72dd40967066969e7eb4b102c6215478a275766bf046665'], // meta
                [Psr6Store::COUNTER_KEY], // write counter
                ['md390aa862a7f27c16d72dd40967066969e7eb4b102c6215478a275766bf046665'] // meta again
            )
            ->willReturnOnConsecutiveCalls($contentDigestCacheItem, $cacheItem, $cacheItem, $cacheItem);

        $cache
            ->expects($this->any())
            ->method('saveDeferred')
            ->willReturn(true);

        $store = new Psr6Store([
            'cache' => $cache,
            'lock_factory' => $this->createMock(LockFactory::class),
        ]);

        $response = new Response('foobar', 200, $responseHeaders);
        $request = Request::create('https://foobar.com/');
        $store->write($request, $response);
    }

    public function contentDigestExpiryProvider()
    {
        yield 'Test no previous response should take the same max age as the current response' => [
            ['Cache-Control' => 's-maxage=600, public'],
            600,
            0,
        ];

        yield 'Previous max-age was higher, digest expiration should not be touched then' => [
            ['Cache-Control' => 's-maxage=600, public'],
            900,
            900,
        ];

        yield 'Previous max-age was lower, digest expiration should be updated' => [
            ['Cache-Control' => 's-maxage=1800, public'],
            1800,
            900,
        ];
    }

    /**
     * @param null $store
     */
    private function getCache($store = null): TagAwareAdapterInterface
    {
        if (null === $store) {
            $store = $this->store;
        }

        $reflection = new \ReflectionClass($store);
        $cache = $reflection->getProperty('cache');
        $cache->setAccessible(true);

        return $cache->getValue($this->store);
    }
}
