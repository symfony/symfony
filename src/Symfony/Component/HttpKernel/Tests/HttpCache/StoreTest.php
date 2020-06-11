<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\HttpCache;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\Store;

class StoreTest extends TestCase
{
    protected $request;
    protected $response;

    /**
     * @var Store
     */
    protected $store;

    protected function setUp()
    {
        $this->request = Request::create('/');
        $this->response = new Response('hello world', 200, []);

        HttpCacheTestCase::clearDirectory(sys_get_temp_dir().'/http_cache');

        $this->store = new Store(sys_get_temp_dir().'/http_cache');
    }

    protected function tearDown()
    {
        $this->store = null;
        $this->request = null;
        $this->response = null;

        HttpCacheTestCase::clearDirectory(sys_get_temp_dir().'/http_cache');
    }

    public function testReadsAnEmptyArrayWithReadWhenNothingCachedAtKey()
    {
        $this->assertEmpty($this->getStoreMetadata('/nothing'));
    }

    public function testUnlockFileThatDoesExist()
    {
        $this->storeSimpleEntry();
        $this->store->lock($this->request);

        $this->assertTrue($this->store->unlock($this->request));
    }

    public function testUnlockFileThatDoesNotExist()
    {
        $this->assertFalse($this->store->unlock($this->request));
    }

    public function testRemovesEntriesForKeyWithPurge()
    {
        $request = Request::create('/foo');
        $this->store->write($request, new Response('foo'));

        $metadata = $this->getStoreMetadata($request);
        $this->assertNotEmpty($metadata);

        $this->assertTrue($this->store->purge('/foo'));
        $this->assertEmpty($this->getStoreMetadata($request));

        // cached content should be kept after purging
        $path = $this->store->getPath($metadata[0][1]['x-content-digest'][0]);
        $this->assertTrue(is_file($path));

        $this->assertFalse($this->store->purge('/bar'));
    }

    public function testStoresACacheEntry()
    {
        $cacheKey = $this->storeSimpleEntry();

        $this->assertNotEmpty($this->getStoreMetadata($cacheKey));
    }

    public function testSetsTheXContentDigestResponseHeaderBeforeStoring()
    {
        $cacheKey = $this->storeSimpleEntry();
        $entries = $this->getStoreMetadata($cacheKey);
        list(, $res) = $entries[0];

        $this->assertEquals('en9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08', $res['x-content-digest'][0]);
    }

    public function testDoesNotTrustXContentDigestFromUpstream()
    {
        $response = new Response('test', 200, ['X-Content-Digest' => 'untrusted-from-elsewhere']);

        $cacheKey = $this->store->write($this->request, $response);
        $entries = $this->getStoreMetadata($cacheKey);
        list(, $res) = $entries[0];

        $this->assertEquals('en9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08', $res['x-content-digest'][0]);
        $this->assertEquals('en9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08', $response->headers->get('X-Content-Digest'));
    }

    public function testWritesResponseEvenIfXContentDigestIsPresent()
    {
        // Prime the store
        $this->store->write($this->request, new Response('test', 200, ['X-Content-Digest' => 'untrusted-from-elsewhere']));

        $response = $this->store->lookup($this->request);
        $this->assertNotNull($response);
    }

    public function testWritingARestoredResponseDoesNotCorruptCache()
    {
        /*
         * This covers the regression reported in https://github.com/symfony/symfony/issues/37174.
         *
         * A restored response does *not* load the body, but only keep the file path in a special X-Body-File
         * header. For reasons (?), the file path was also used as the restored response body.
         * It would be up to others (HttpCache...?) to honor this header and actually load the response content
         * from there.
         *
         * When a restored response was stored again, the Store itself would ignore the header. In the first
         * step, this would compute a new Content Digest based on the file path in the restored response body;
         * this is covered by "Checkpoint 1" below. But, since the X-Body-File header was left untouched (Checkpoint 2), downstream
         * code (HttpCache...) would not immediately notice.
         *
         * Only upon performing the lookup for a second time, we'd get a Response where the (wrong) Content Digest
         * is also reflected in the X-Body-File header, this time also producing wrong content when the downstream
         * evaluates it.
         */
        $this->store->write($this->request, $this->response);
        $digest = $this->response->headers->get('X-Content-Digest');
        $path = $this->getStorePath($digest);

        $response = $this->store->lookup($this->request);
        $this->store->write($this->request, $response);
        $this->assertEquals($digest, $response->headers->get('X-Content-Digest')); // Checkpoint 1
        $this->assertEquals($path, $response->headers->get('X-Body-File')); // Checkpoint 2

        $response = $this->store->lookup($this->request);
        $this->assertEquals($digest, $response->headers->get('X-Content-Digest'));
        $this->assertEquals($path, $response->headers->get('X-Body-File'));
    }

    public function testFindsAStoredEntryWithLookup()
    {
        $this->storeSimpleEntry();
        $response = $this->store->lookup($this->request);

        $this->assertNotNull($response);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
    }

    public function testDoesNotFindAnEntryWithLookupWhenNoneExists()
    {
        $request = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar']);

        $this->assertNull($this->store->lookup($request));
    }

    public function testCanonizesUrlsForCacheKeys()
    {
        $this->storeSimpleEntry($path = '/test?x=y&p=q');
        $hitsReq = Request::create($path);
        $missReq = Request::create('/test?p=x');

        $this->assertNotNull($this->store->lookup($hitsReq));
        $this->assertNull($this->store->lookup($missReq));
    }

    public function testDoesNotFindAnEntryWithLookupWhenTheBodyDoesNotExist()
    {
        $this->storeSimpleEntry();
        $this->assertNotNull($this->response->headers->get('X-Content-Digest'));
        $path = $this->getStorePath($this->response->headers->get('X-Content-Digest'));
        @unlink($path);
        $this->assertNull($this->store->lookup($this->request));
    }

    public function testRestoresResponseHeadersProperlyWithLookup()
    {
        $this->storeSimpleEntry();
        $response = $this->store->lookup($this->request);

        $this->assertEquals($response->headers->all(), array_merge(['content-length' => 4, 'x-body-file' => [$this->getStorePath($response->headers->get('X-Content-Digest'))]], $this->response->headers->all()));
    }

    public function testRestoresResponseContentFromEntityStoreWithLookup()
    {
        $this->storeSimpleEntry();
        $response = $this->store->lookup($this->request);
        $this->assertEquals($this->getStorePath('en'.hash('sha256', 'test')), $response->getContent());
    }

    public function testInvalidatesMetaAndEntityStoreEntriesWithInvalidate()
    {
        $this->storeSimpleEntry();
        $this->store->invalidate($this->request);
        $response = $this->store->lookup($this->request);
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $response);
        $this->assertFalse($response->isFresh());
    }

    public function testSucceedsQuietlyWhenInvalidateCalledWithNoMatchingEntries()
    {
        $req = Request::create('/test');
        $this->store->invalidate($req);
        $this->assertNull($this->store->lookup($this->request));
    }

    public function testDoesNotReturnEntriesThatVaryWithLookup()
    {
        $req1 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar']);
        $req2 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Bling', 'HTTP_BAR' => 'Bam']);
        $res = new Response('test', 200, ['Vary' => 'Foo Bar']);
        $this->store->write($req1, $res);

        $this->assertNull($this->store->lookup($req2));
    }

    public function testDoesNotReturnEntriesThatSlightlyVaryWithLookup()
    {
        $req1 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar']);
        $req2 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bam']);
        $res = new Response('test', 200, ['Vary' => ['Foo', 'Bar']]);
        $this->store->write($req1, $res);

        $this->assertNull($this->store->lookup($req2));
    }

    public function testStoresMultipleResponsesForEachVaryCombination()
    {
        $req1 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar']);
        $res1 = new Response('test 1', 200, ['Vary' => 'Foo Bar']);
        $key = $this->store->write($req1, $res1);

        $req2 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Bling', 'HTTP_BAR' => 'Bam']);
        $res2 = new Response('test 2', 200, ['Vary' => 'Foo Bar']);
        $this->store->write($req2, $res2);

        $req3 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Baz', 'HTTP_BAR' => 'Boom']);
        $res3 = new Response('test 3', 200, ['Vary' => 'Foo Bar']);
        $this->store->write($req3, $res3);

        $this->assertEquals($this->getStorePath('en'.hash('sha256', 'test 3')), $this->store->lookup($req3)->getContent());
        $this->assertEquals($this->getStorePath('en'.hash('sha256', 'test 2')), $this->store->lookup($req2)->getContent());
        $this->assertEquals($this->getStorePath('en'.hash('sha256', 'test 1')), $this->store->lookup($req1)->getContent());

        $this->assertCount(3, $this->getStoreMetadata($key));
    }

    public function testOverwritesNonVaryingResponseWithStore()
    {
        $req1 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar']);
        $res1 = new Response('test 1', 200, ['Vary' => 'Foo Bar']);
        $this->store->write($req1, $res1);
        $this->assertEquals($this->getStorePath('en'.hash('sha256', 'test 1')), $this->store->lookup($req1)->getContent());

        $req2 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Bling', 'HTTP_BAR' => 'Bam']);
        $res2 = new Response('test 2', 200, ['Vary' => 'Foo Bar']);
        $this->store->write($req2, $res2);
        $this->assertEquals($this->getStorePath('en'.hash('sha256', 'test 2')), $this->store->lookup($req2)->getContent());

        $req3 = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar']);
        $res3 = new Response('test 3', 200, ['Vary' => 'Foo Bar']);
        $key = $this->store->write($req3, $res3);
        $this->assertEquals($this->getStorePath('en'.hash('sha256', 'test 3')), $this->store->lookup($req3)->getContent());

        $this->assertCount(2, $this->getStoreMetadata($key));
    }

    public function testLocking()
    {
        $req = Request::create('/test', 'get', [], [], [], ['HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar']);
        $this->assertTrue($this->store->lock($req));

        $this->store->lock($req);
        $this->assertTrue($this->store->isLocked($req));

        $this->store->unlock($req);
        $this->assertFalse($this->store->isLocked($req));
    }

    public function testPurgeHttps()
    {
        $request = Request::create('https://example.com/foo');
        $this->store->write($request, new Response('foo'));

        $this->assertNotEmpty($this->getStoreMetadata($request));

        $this->assertTrue($this->store->purge('https://example.com/foo'));
        $this->assertEmpty($this->getStoreMetadata($request));
    }

    public function testPurgeHttpAndHttps()
    {
        $requestHttp = Request::create('https://example.com/foo');
        $this->store->write($requestHttp, new Response('foo'));

        $requestHttps = Request::create('http://example.com/foo');
        $this->store->write($requestHttps, new Response('foo'));

        $this->assertNotEmpty($this->getStoreMetadata($requestHttp));
        $this->assertNotEmpty($this->getStoreMetadata($requestHttps));

        $this->assertTrue($this->store->purge('http://example.com/foo'));
        $this->assertEmpty($this->getStoreMetadata($requestHttp));
        $this->assertEmpty($this->getStoreMetadata($requestHttps));
    }

    protected function storeSimpleEntry($path = null, $headers = [])
    {
        if (null === $path) {
            $path = '/test';
        }

        $this->request = Request::create($path, 'get', [], [], [], $headers);
        $this->response = new Response('test', 200, ['Cache-Control' => 'max-age=420']);

        return $this->store->write($this->request, $this->response);
    }

    protected function getStoreMetadata($key)
    {
        $r = new \ReflectionObject($this->store);
        $m = $r->getMethod('getMetadata');
        $m->setAccessible(true);

        if ($key instanceof Request) {
            $m1 = $r->getMethod('getCacheKey');
            $m1->setAccessible(true);
            $key = $m1->invoke($this->store, $key);
        }

        return $m->invoke($this->store, $key);
    }

    protected function getStorePath($key)
    {
        $r = new \ReflectionObject($this->store);
        $m = $r->getMethod('getPath');
        $m->setAccessible(true);

        return $m->invoke($this->store, $key);
    }
}
