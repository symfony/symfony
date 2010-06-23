<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\HttpKernel\Cache;

require_once __DIR__.'/CacheTestCase.php';

use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\Response;
use Symfony\Components\HttpKernel\Cache\Store;
use Symfony\Tests\Components\HttpKernel\Cache\CacheTestCase;

class CacheStoreTest extends \PHPUnit_Framework_TestCase
{
    protected $request;
    protected $response;
    protected $store;

    public function setUp()
    {
        $this->request = Request::create('/');
        $this->response = new Response('hello world', 200, array());

        CacheTestCase::clearDirectory(sys_get_temp_dir().'/http_cache');

        $this->store = new Store(sys_get_temp_dir().'/http_cache');
    }

    public function tearDown()
    {
        $this->store = null;

        CacheTestCase::clearDirectory(sys_get_temp_dir().'/http_cache');
    }

    public function testReadsAnEmptyArrayWithReadWhenNothingCachedAtKey()
    {
        $this->assertEmpty($this->store->getMetadata('/nothing'));
    }

    public function testRemovesEntriesForKeyWithPurge()
    {
        $request = Request::create('/foo');
        $this->store->write($request, new Response('foo'));
        $this->assertNotEmpty($this->store->getMetadata($this->store->getCacheKey($request)));

        $this->assertNull($this->store->purge('/foo'));
        $this->assertEmpty($this->store->getMetadata($this->store->getCacheKey($request)));
    }

    public function testStoresACacheEntry()
    {
        $cacheKey = $this->storeSimpleEntry();

        $this->assertNotEmpty($this->store->getMetadata($cacheKey));
    }

    public function testSetsTheXContentDigestResponseHeaderBeforeStoring()
    {
        $cacheKey = $this->storeSimpleEntry();
        $entries = $this->store->getMetadata($cacheKey);
        list ($req, $res) = $entries[0];

        $this->assertEquals('ena94a8fe5ccb19ba61c4c0873d391e987982fbbd3', $res['x-content-digest'][0]);
    }

    public function testFindsAStoredEntryWithLookup()
    {
        $this->storeSimpleEntry();
        $response = $this->store->lookup($this->request);

        $this->assertNotNull($response);
        $this->assertInstanceOf('Symfony\Components\HttpKernel\Response', $response);
    }

    public function testDoesNotFindAnEntryWithLookupWhenNoneExists()
    {
        $request = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar'));

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
        $path = $this->store->getPath($this->response->headers->get('X-Content-Digest'));
        @unlink($path);
        $this->assertNull($this->store->lookup($this->request));
    }

    public function testRestoresResponseHeadersProperlyWithLookup()
    {
        $this->storeSimpleEntry();
        $response = $this->store->lookup($this->request);

        $this->assertEquals($response->headers->all(), array_merge(array('content-length' => 4, 'x-body-file' => array($this->store->getPath($response->headers->get('X-Content-Digest')))), $this->response->headers->all()));
    }

    public function testRestoresResponseContentFromEntityStoreWithLookup()
    {
        $this->storeSimpleEntry();
        $response = $this->store->lookup($this->request);
        $this->assertEquals($this->store->getPath('en'.sha1('test')), $response->getContent());
    }

    public function testInvalidatesMetaAndEntityStoreEntriesWithInvalidate()
    {
        $this->storeSimpleEntry();
        $this->store->invalidate($this->request);
        $response = $this->store->lookup($this->request);
        $this->assertInstanceOf('Symfony\Components\HttpKernel\Response', $response);
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
        $req1 = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar'));
        $req2 = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Bling', 'HTTP_BAR' => 'Bam'));
        $res = new Response('test', 200, array('Vary' => 'Foo Bar'));
        $this->store->write($req1, $res);

        $this->assertNull($this->store->lookup($req2));
    }

    public function testStoresMultipleResponsesForEachVaryCombination()
    {
        $req1 = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar'));
        $res1 = new Response('test 1', 200, array('Vary' => 'Foo Bar'));
        $key = $this->store->write($req1, $res1);

        $req2 = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Bling', 'HTTP_BAR' => 'Bam'));
        $res2 = new Response('test 2', 200, array('Vary' => 'Foo Bar'));
        $this->store->write($req2, $res2);

        $req3 = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Baz', 'HTTP_BAR' => 'Boom'));
        $res3 = new Response('test 3', 200, array('Vary' => 'Foo Bar'));
        $this->store->write($req3, $res3);

        $this->assertEquals($this->store->getPath('en'.sha1('test 3')), $this->store->lookup($req3)->getContent());
        $this->assertEquals($this->store->getPath('en'.sha1('test 2')), $this->store->lookup($req2)->getContent());
        $this->assertEquals($this->store->getPath('en'.sha1('test 1')), $this->store->lookup($req1)->getContent());

        $this->assertEquals(3, count($this->store->getMetadata($key)));
    }

    public function testOverwritesNonVaryingResponseWithStore()
    {
        $req1 = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar'));
        $res1 = new Response('test 1', 200, array('Vary' => 'Foo Bar'));
        $key = $this->store->write($req1, $res1);
        $this->assertEquals($this->store->getPath('en'.sha1('test 1')), $this->store->lookup($req1)->getContent());

        $req2 = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Bling', 'HTTP_BAR' => 'Bam'));
        $res2 = new Response('test 2', 200, array('Vary' => 'Foo Bar'));
        $this->store->write($req2, $res2);
        $this->assertEquals($this->store->getPath('en'.sha1('test 2')), $this->store->lookup($req2)->getContent());

        $req3 = Request::create('/test', 'get', array(), array(), array(), array('HTTP_FOO' => 'Foo', 'HTTP_BAR' => 'Bar'));
        $res3 = new Response('test 3', 200, array('Vary' => 'Foo Bar'));
        $key = $this->store->write($req3, $res3);
        $this->assertEquals($this->store->getPath('en'.sha1('test 3')), $this->store->lookup($req3)->getContent());

        $this->assertEquals(2, count($this->store->getMetadata($key)));
    }

    protected function storeSimpleEntry($path = null, $headers = array())
    {
        if (null === $path) {
            $path = '/test';
        }

        $this->request = Request::create($path, 'get', array(), array(), array(), $headers);
        $this->response = new Response('test', 200, array('Cache-Control' => 'max-age=420'));

        return $this->store->write($this->request, $this->response);
    }
}
