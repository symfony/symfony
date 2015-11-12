<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Profiler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Dumper\TraceableDumper;
use Symfony\Component\VarDumper\Profiler\DumpData;
use Symfony\Component\VarDumper\Profiler\DumpDataCollector;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DumpDataCollectorTest extends \PHPUnit_Framework_TestCase
{

    public function testCollectDefault()
    {
        $data = new Data(array(array(123)));

        $traceableDumper = new TraceableDumper();

        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $collector = new DumpDataCollector($requestStack, $traceableDumper);
        $response = new Response('<html><body></body></html>', 200, array('X-Debug-Token' => 'test'));
        $request->setRequestFormat('html');

        $collector->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $request, HttpKernelInterface::MASTER_REQUEST, $response
            )
        );
        $traceableDumper->dump($data);
        $line = __LINE__ - 1;

        /** @var DumpData $data */
        $data = $collector->getCollectedData();
        $this->assertInstanceof('Symfony\Component\VarDumper\Profiler\DumpData', $data);
        $this->assertEquals(1, $data->getDumpsCount());
        $dumps = $data->getDumps('html');
        $this->assertCount(1, $dumps);
        $this->assertEquals($line, $dumps[0]['line']);
        $this->assertEquals(__FILE__, $dumps[0]['file']);
    }

    public function testCollectRedirectResponse()
    {
        $data = new Data(array(array(123)));

        $traceableDumper = new TraceableDumper();

        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $collector = new DumpDataCollector($requestStack, $traceableDumper);

        ob_start();
        $traceableDumper->dump($data);
        $line = __LINE__ - 1;
        unset($traceableDumper);
        unset($collector);
        $output = ob_get_clean();


        if (PHP_VERSION_ID >= 50400) {
            $this->assertSame("DumpDataCollectorTest.php on line {$line}:\n123\n", $output);
        } else {
            $this->assertSame("\"DumpDataCollectorTest.php on line {$line}:\"\n123\n", $output);
        }
    }

    public function testCollectWithoutRequestOrResponse()
    {
        $requestStack = new RequestStack();
        $collector = new DumpDataCollector($requestStack, new TraceableDumper());
        $this->assertNULL($collector->getCollectedData());
        $request1 = new Request();
        $request2 = new Request();
        $requestStack->push($request1);
        $requestStack->push($request2);

        $collector->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $request1, HttpKernelInterface::MASTER_REQUEST, new Response('', 200, array('Content-Type' => 'text/html'))
            )
        );
        $collector->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $request2, HttpKernelInterface::SUB_REQUEST, new Response('', 200, array('Content-Type' => 'text/html'))
            )
        );
        $this->assertNULL($collector->getCollectedData());
        $requestStack->pop();
        $this->assertNotNULL($collector->getCollectedData());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid dump format: json
     */
    public function testDumpsUnsupportedFormat()
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $requestStack->push($request);
        $collector = new DumpDataCollector($requestStack, new TraceableDumper());
        $response = new Response('', 200, array('Content-Type' => 'text/html'));
        $collector->onKernelResponse(
            new FilterResponseEvent(
                $this->getKernel(), $requestStack->getMasterRequest(), HttpKernelInterface::MASTER_REQUEST, $response
            )
        );
        $data = $collector->getCollectedData();
        $data->getDumps('json');
    }

    public function testSubscribedEvents()
    {
        $events = DumpDataCollector::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    protected function getKernel()
    {
        return $this->getMock('Symfony\Component\HttpKernel\KernelInterface');
    }
}
