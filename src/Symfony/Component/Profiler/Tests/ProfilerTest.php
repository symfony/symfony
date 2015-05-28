<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Profiler\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Profiler\Storage\SqliteProfilerStorage;
use Symfony\Component\Profiler\HttpProfiler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfilerTest extends \PHPUnit_Framework_TestCase
{
    private $tmp;
    private $storage;

    public function testCollect()
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->query->set('foo', 'bar');
        $requestStack->push($request);
        $response = new Response('', 204);
        $collector = new RequestDataCollector($requestStack);
        $collector->onKernelResponse(
            new FilterResponseEvent(
                $this->getMock('Symfony\Component\HttpKernel\KernelInterface'),
                $requestStack->getMasterRequest(),
                HttpKernelInterface::MASTER_REQUEST,
                $response
            )
        );
        $profiler = new HttpProfiler($requestStack, $this->storage);
        $profiler->add($collector);
        $profiler->addResponse($request, $response);

        $profile = $profiler->collect();

        $this->assertSame(204, $profile->getStatusCode());
        $this->assertSame('GET', $profile->getMethod());
        $this->assertEquals(array('foo' => 'bar'), $profile->getProfileData('request')->getRequestQuery()->all());
    }

    public function testFindWorksWithDates()
    {
        $profiler = new HttpProfiler(new RequestStack(), $this->storage);

        $this->assertCount(0, $profiler->find(null, null, null, null, '7th April 2014', '9th April 2014'));
    }

    public function testFindWorksWithTimestamps()
    {
        $profiler = new HttpProfiler(new RequestStack(), $this->storage);

        $this->assertCount(0, $profiler->find(null, null, null, null, '1396828800', '1397001600'));
    }

    public function testFindWorksWithInvalidDates()
    {
        $profiler = new HttpProfiler(new RequestStack(), $this->storage);

        $this->assertCount(0, $profiler->find(null, null, null, null, 'some string', ''));
    }

    protected function setUp()
    {
        if (!class_exists('SQLite3') && (!class_exists('PDO') || !in_array('sqlite', \PDO::getAvailableDrivers()))) {
            $this->markTestSkipped('This test requires SQLite support in your environment');
        }

        $this->tmp = tempnam(sys_get_temp_dir(), 'sf2_profiler');
        if (file_exists($this->tmp)) {
            @unlink($this->tmp);
        }

        $this->storage = new SqliteProfilerStorage('sqlite:'.$this->tmp);
        $this->storage->purge();
    }

    protected function tearDown()
    {
        if (null !== $this->storage) {
            $this->storage->purge();
            $this->storage = null;

            @unlink($this->tmp);
        }
    }
}
