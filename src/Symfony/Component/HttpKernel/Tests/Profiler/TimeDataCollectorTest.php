<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Profiler;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Profiler\TimeDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Stopwatch\Stopwatch;

class TimeDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $requestStack = new RequestStack();
        $c = new TimeDataCollector($requestStack);

        $request = new Request();
        $requestStack->push($request);

        $data = $c->getCollectedData();
        $data->setToken('Mock-Test-Token');

        $this->assertEquals(0, $data->getStartTime());
        $this->assertEquals(0, $data->getInitTime());
        $this->assertEquals(0, $data->getDuration());
    }

    public function testCollectServerRequestTime()
    {
        $requestStack = new RequestStack();
        $c = new TimeDataCollector($requestStack);

        $request = new Request();
        $request->server->set('REQUEST_TIME', 1);
        $requestStack->push($request);

        $data = $c->getCollectedData();
        $this->assertEquals(1000, $data->getStartTime());
    }

    public function testCollectServerRequestTimeFloat()
    {
        $requestStack = new RequestStack();
        $c = new TimeDataCollector($requestStack);

        $request = new Request();
        $request->server->set('REQUEST_TIME_FLOAT', 2);
        $requestStack->push($request);

        $data = $c->getCollectedData();
        $this->assertEquals(2000, $data->getStartTime());
    }

    public function testCollectWithKernel()
    {
        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $kernel->expects($this->once())->method('getStartTime')->will($this->returnValue(123456));

        $requestStack = new RequestStack();
        $c = new TimeDataCollector($requestStack, $kernel);

        $request = new Request();
        $request->server->set('REQUEST_TIME_FLOAT', 2);
        $requestStack->push($request);

        $data = $c->getCollectedData();
        $this->assertEquals(123456000, $data->getStartTime());
    }

    public function testCollectWithStopwatch()
    {
        $requestStack = new RequestStack();
        $stopwatch = new Stopwatch();
        $startTime = microtime(true) - 10;

        $kernel = $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
        $kernel->expects($this->once())->method('getStartTime')->will($this->returnValue($startTime));

        $c = new TimeDataCollector($requestStack, $kernel, $stopwatch);
        $token = 'Mock-Test-Token-Stopwatch';
        $stopwatch->openSection();
        $stopwatch->start('Kernel.Request', 'section');
        sleep(1);
        $stopwatch->stop('Kernel.Request');
        $stopwatch->stopSection($token);

        $request = new Request();
        $requestStack->push($request);

        $data = $c->getCollectedData();
        $data->setToken($token);

        $this->assertGreaterThan(10, $data->getDuration() / 1000);
        $this->assertInternalType('array', $data->getEvents());
        $this->assertGreaterThan(0, $data->getInitTime());
    }
}
