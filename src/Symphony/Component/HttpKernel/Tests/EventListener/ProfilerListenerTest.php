<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\HttpKernel\EventListener\ProfilerListener;
use Symphony\Component\HttpKernel\Event\FilterResponseEvent;
use Symphony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symphony\Component\HttpKernel\Event\PostResponseEvent;
use Symphony\Component\HttpKernel\Exception\HttpException;
use Symphony\Component\HttpKernel\Kernel;

class ProfilerListenerTest extends TestCase
{
    /**
     * Test a master and sub request with an exception and `onlyException` profiler option enabled.
     */
    public function testKernelTerminate()
    {
        $profile = $this->getMockBuilder('Symphony\Component\HttpKernel\Profiler\Profile')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler = $this->getMockBuilder('Symphony\Component\HttpKernel\Profiler\Profiler')
            ->disableOriginalConstructor()
            ->getMock();

        $profiler->expects($this->once())
            ->method('collect')
            ->will($this->returnValue($profile));

        $kernel = $this->getMockBuilder('Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();

        $masterRequest = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $subRequest = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this->getMockBuilder('Symphony\Component\HttpFoundation\Response')
            ->disableOriginalConstructor()
            ->getMock();

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
}
