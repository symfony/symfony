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

require_once __DIR__.'/TestHttpKernel.php';

use Symfony\Components\HttpKernel\Request;
use Symfony\Components\HttpKernel\Cache\Cache;
use Symfony\Components\HttpKernel\Cache\Store;

class CacheTestCase extends \PHPUnit_Framework_TestCase
{
    protected $kernel;
    protected $cache;
    protected $caches;
    protected $cacheConfig;
    protected $request;
    protected $response;
    protected $responses;

    public function setUp()
    {
        $this->kernel = null;

        $this->cache = null;
        $this->caches = array();
        $this->cacheConfig = array();

        $this->request = null;
        $this->response = null;
        $this->responses = array();

        $this->clearDirectory(sys_get_temp_dir().'/http_cache');
    }

    public function tearDown()
    {
        $this->kernel = null;
        $this->cache = null;
        $this->caches = null;
        $this->request = null;
        $this->response = null;
        $this->responses = null;
        $this->cacheConfig = null;

        $this->clearDirectory(sys_get_temp_dir().'/http_cache');
    }

    public function assertHttpKernelIsCalled()
    {
        $this->assertTrue($this->kernel->hasBeenCalled());
    }

    public function assertHttpKernelIsNotCalled()
    {
        $this->assertFalse($this->kernel->hasBeenCalled());
    }

    public function assertResponseOk()
    {
        $this->assertEquals(200, $this->response->getStatusCode());
    }

    public function assertTraceContains($trace)
    {
        $traces = $this->cache->getTraces();
        $traces = current($traces);

        $this->assertRegExp('/'.$trace.'/', implode(', ', $traces));
    }

    public function assertTraceNotContains($trace)
    {
        $traces = $this->cache->getTraces();
        $traces = current($traces);

        $this->assertNotRegExp('/'.$trace.'/', implode(', ', $traces));
    }

    public function request($method, $uri = '/', $server = array(), $cookies = array())
    {
        if (null === $this->kernel) {
            throw new \LogicException('You must call setNextResponse() before calling request().');
        }

        $this->kernel->reset();

        $this->store = new Store(sys_get_temp_dir().'/http_cache');

        $this->cacheConfig['debug'] = true;
        $this->cache = new Cache($this->kernel, $this->store, null, $this->cacheConfig);
        $this->request = Request::create($uri, $method, array(), $cookies, array(), $server);

        $this->response = $this->cache->handle($this->request);

        $this->responses[] = $this->response;
    }

    public function getMetaStorageValues()
    {
        $values = array();
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(sys_get_temp_dir().'/http_cache/md'), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            $values[] = file_get_contents($file);
        }

        return $values;
    }

    // A basic response with 200 status code and a tiny body.
    public function setNextResponse($statusCode = 200, array $headers = array(), $body = 'Hello World', \Closure $customizer = null)
    {
        $called = false;

        $this->kernel = new TestHttpKernel($body, $statusCode, $headers, $customizer);
    }

    static public function clearDirectory($directory)
    {
        if (!is_dir($directory)) {
            return;
        }

        $fp = opendir($directory);
        while (false !== $file = readdir($fp)) {
            if (!in_array($file, array('.', '..')))
            {
                if (is_link($directory.'/'.$file)) {
                    unlink($directory.'/'.$file);
                } else if (is_dir($directory.'/'.$file)) {
                    self::clearDirectory($directory.'/'.$file);
                    rmdir($directory.'/'.$file);
                } else {
                    unlink($directory.'/'.$file);
                }
            }
        }

        closedir($fp);
    }
}
