<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests\DataCollector;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Profiler\DataCollector\TimeDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class TimeDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $requestStack = new RequestStack();
        $c = new TimeDataCollector($requestStack);

        $request = new Request();
        $requestStack->push($request);

        $c->setToken('Mock-Test-Token');

        $data = $c->lateCollect();
        $this->assertEquals(0, $data->getStartTime());
    }

    public function testCollectServerRequestTime()
    {
        $requestStack = new RequestStack();
        $c = new TimeDataCollector($requestStack);

        $request = new Request();
        $request->server->set('REQUEST_TIME', 1);
        $requestStack->push($request);

        $data = $c->lateCollect();
        $this->assertEquals(1000, $data->getStartTime());

    }

    public function testCollectServerRequestTimeFloat()
    {
        $requestStack = new RequestStack();
        $c = new TimeDataCollector($requestStack);

        $request = new Request();
        $request->server->set('REQUEST_TIME_FLOAT', 2);
        $requestStack->push($request);

        $data = $c->lateCollect();
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

        $data = $c->lateCollect();
        $this->assertEquals(123456000, $data->getStartTime());
    }
}
