<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Symfony\Component\Cache\CacheProfiler;
use Symfony\Component\Stopwatch\Stopwatch;

class CacheProfilerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var CacheProfiler
     */
    protected $cacheProfiler;

    protected function setUp()
    {
        $this->cacheProfiler = new CacheProfiler(new Stopwatch());
    }

    protected function tearDown()
    {
        unset($this->cacheProfiler);
    }

    /**
     * @covers Symfony\Component\Cache\CacheProfiler::start
     */
    public function testStart()
    {
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->start('type', 'name', 'get'));
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->start('type', 'name', 'get', 'demo'));
    }

    /**
     * @covers Symfony\Component\Cache\CacheProfiler::stop
     * @depends testStart
     *
     */
    public function testStop()
    {
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->start('type', 'name', 'get', null));
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->start('type', 'name', 'get2', null));
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->start('type', 'name', 'get', 'demo'));
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->start('type', 'name', 'get2', 'demo'));

        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->stop('type', 'name', 'get', null, true));
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->stop('type', 'name', 'get2', null, false));
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->stop('type', 'name', 'get', 'demo', true));
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->stop('type', 'name', 'get2', 'demo', false));
    }

    /**
     * @covers Symfony\Component\Cache\CacheProfiler::getResults
     */
    public function testGetResults()
    {
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->start('type', 'name', 'get', null));
        $this->assertInstanceOf('Symfony\\Component\\Cache\\CacheProfiler', $this->cacheProfiler->stop('type', 'name', 'get', null, true));

        $results = $this->cacheProfiler->getResults();

        $this->assertCount(4, $results);

        $this->assertArrayHasKey('hits', $results);
        $this->assertArrayHasKey('ops', $results);
        $this->assertArrayHasKey('time', $results);

        $this->assertArrayHasKey('drivers', $results);

        $this->assertArrayHasKey('type', $results['drivers']);
        $this->assertCount(1, $results['drivers']);

        $this->assertArrayHasKey('name', $results['drivers']['type']);
        $this->assertCount(1, $results['drivers']['type']);

        $this->assertTrue(is_array($results['drivers']['type']['name']));
        $this->assertCount(1, $results['drivers']['type']['name']);

        $this->assertTrue(is_array($results['drivers']['type']['name']['get']));
        $this->assertCount(1, $results['drivers']['type']['name']['get']);

        $result = array_pop($results['drivers']['type']['name']['get']);
        $this->assertCount(2, $result);

        $this->assertArrayHasKey('result', $result);
        $this->assertEquals(1, $result['result']);

        $this->assertArrayHasKey('duration', $result);
    }
}
