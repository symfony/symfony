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

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Kernel;

class ProfilerListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test to ensure BC without RequestStack
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0.
     */
    public function testEventsWithoutRequestStack()
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

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this->getMockBuilder('Symfony\Component\HttpFoundation\Response')
            ->disableOriginalConstructor()
            ->getMock();

        $listener = new ProfilerListener($profiler);
        $listener->onKernelRequest(new GetResponseEvent($kernel, $request, Kernel::MASTER_REQUEST));
        $listener->onKernelResponse(new FilterResponseEvent($kernel, $request, Kernel::MASTER_REQUEST, $response));
        $listener->onKernelTerminate(new PostResponseEvent($kernel, $request, $response));
    }
}
