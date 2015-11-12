<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelEvents;

class ProfilerListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test to ensure BC without RequestStack.
     *
     * @group legacy
     */
    public function testLegacyEventsWithoutRequestStack()
    {
        $profile = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profile')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler->expects($this->once())
            ->method('collect')
            ->will($this->returnValue($profile));

        $profiler->expects($this->any())
            ->method('getDeprecatedDataCollectors')
            ->will($this->returnValue(array()));

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $response = new Response();

        $listener = new ProfilerListener($profiler);
        $listener->onKernelRequest(new GetResponseEvent($kernel, $request, Kernel::MASTER_REQUEST));
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $request, Kernel::MASTER_REQUEST, $response));
        $listener->onKernelTerminate(new PostResponseEvent($kernel, $request, $response));
    }

    /**
     * Test to ensure BC with Symfony\Component\HttpKernel\Profiler\Profiler
     *
     * Test a master and sub request with an exception and `onlyException` profiler option enabled.
     *
     * @group legacy
     */
    public function testLegacyKernelTerminate()
    {
        $profile = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profile')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler->expects($this->once())
            ->method('collect')
            ->will($this->returnValue($profile));

        $profiler->expects($this->any())
            ->method('getDeprecatedDataCollectors')
            ->will($this->returnValue(array()));

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $masterRequest = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $subRequest = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $response = new Response();

        $requestStack = new RequestStack();
        $requestStack->push($masterRequest);

        $onlyException = true;
        $listener = new ProfilerListener($profiler, $requestStack, null, $onlyException);

        // master request
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $masterRequest, Kernel::MASTER_REQUEST, $response));

        // sub request
        $listener->onKernelException(new GetResponseForExceptionEvent($kernel, $subRequest, Kernel::SUB_REQUEST, new HttpException(404)));
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $subRequest, Kernel::SUB_REQUEST, $response));

        $listener->onKernelTerminate(new PostResponseEvent($kernel, $masterRequest, $response));
    }

    /**
     * Test a master and sub request with an exception and `onlyException` profiler option enabled.
     */
    public function testKernelTerminate()
    {
        $profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $profile = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profile')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler->expects($this->once())
            ->method('collect')
            ->will($this->returnValue($profile));

        $profiler->expects($this->any())
            ->method('getDeprecatedDataCollectors')
            ->will($this->returnValue(array()));

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $masterRequest = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $subRequest = new Request();
        $subRequest2 = new Request();
        $response = new Response();

        $requestStack = new RequestStack();
        $requestStack->push($masterRequest);
        $requestStack->push($subRequest);
        $requestStack->push($subRequest2);

        $listener = new ProfilerListener($profiler, $requestStack, null, true, false);

        // master request
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $masterRequest, Kernel::MASTER_REQUEST, $response));

        // sub request
        $listener->onKernelException(new GetResponseForExceptionEvent($kernel, $subRequest, Kernel::SUB_REQUEST, new HttpException(404)));
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $subRequest, Kernel::SUB_REQUEST, $response));

        $listener->onKernelTerminate(new PostResponseEvent($kernel, $masterRequest, $response));
    }

    /**
     * Test a master and sub request with an exception and `onlyException` profiler option enabled.
     */
    public function testKernelExceptionOnlyMasterWithSub()
    {
        $profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $subRequest = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $requestStack = new RequestStack();

        $onlyException = true;
        $listener = new ProfilerListener($profiler, $requestStack, null, $onlyException, true);

        // sub request
        $listener->onKernelException(new GetResponseForExceptionEvent($kernel, $subRequest, Kernel::SUB_REQUEST, new HttpException(404)));
    }

    /**
     * Test a master and sub request with an exception and `onlyException` profiler option enabled.
     */
    public function testKernelResponseOnlyMasterWithSub()
    {
        $profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler->expects($this->never())
            ->method('collect');

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $subRequest = new Request();

        $response = new Response();

        $requestStack = new RequestStack();

        $listener = new ProfilerListener($profiler, $requestStack, null, false, true);

        // sub request
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $subRequest, Kernel::SUB_REQUEST, $response));
    }

    /**
     * Test a master and sub request with an exception and `onlyException` profiler option enabled.
     */
    public function testKernelResponseNoProfileReturned()
    {
        $profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler->expects($this->once())
            ->method('collect')
            ->will($this->returnValue(null));

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $masterRequest = new Request();
        $response = new Response();

        $requestStack = new RequestStack();
        $requestStack->push($masterRequest);

        $listener = new ProfilerListener($profiler, $requestStack, null, false, false);

        // master request
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $masterRequest, Kernel::MASTER_REQUEST, $response));
    }

    /**
     * Test a master and sub request with an exception and `onlyException` profiler option enabled.
     */
    public function testKernelResponseWithMatcher()
    {
        $profiler = $this->getMockBuilder('Symfony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler->expects($this->never())
            ->method('collect');

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $matcher = $this->getMock('Symfony\Component\HttpFoundation\RequestMatcherInterface');
        $matcher->expects($this->once())
            ->method('matches')
            ->willReturn(false);

        $masterRequest = new Request();
        $response = new Response();

        $requestStack = new RequestStack();
        $requestStack->push($masterRequest);

        $listener = new ProfilerListener($profiler, $requestStack, $matcher, false, false);

        // master request
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $masterRequest, Kernel::MASTER_REQUEST, $response));
    }

    public function testSubscribedEvents()
    {
        $events = ProfilerListener::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::TERMINATE, $events);
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
    }
}
