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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * SessionListenerTest.
 *
 * Tests SessionListener.
 */
class SessionListenerTest extends TestCase
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var SessionListener
     */
    private $listener;

    /**
     * @var SessionInterface
     */
    private $session;

    protected function setUp()
    {
        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->listener = $this->getMockForAbstractClass(SessionListener::class);
        $this->session = $this->getSession();
    }

    public function testShouldSetSessionOnMasterRequest()
    {
        $this->sessionIsDefined();
        $this->sessionMustBeSet();

        $this->kernelRequest($this->request);
    }

    public function testShouldNotSetSessionOnSubRequest()
    {
        $this->sessionIsDefined();
        $this->sessionMustNotBeSet();

        $this->kernelRequest(new Request(), HttpKernelInterface::SUB_REQUEST);
    }

    public function testShouldNotSetNullSession()
    {
        $this->sessionIsNull();
        $this->sessionMustNotBeSet();

        $this->kernelRequest(new Request());
    }

    public function testShouldNotReplaceSession()
    {
        $this->sessionIsDefined();
        $this->sessionMustNotBeSet();

        $this->kernelRequest(new Request());
    }

    private function kernelRequest(Request $request, $type = HttpKernelInterface::MASTER_REQUEST)
    {
        $response = new Response();
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $event = new GetResponseEvent($kernel, $request, $type);
        $event->setResponse($response);

        $this->listener->onKernelRequest($event);

        $this->assertSame($response, $event->getResponse());
    }

    private function sessionIsDefined()
    {
        $this->listener->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue($this->session));
    }

    private function sessionIsNull()
    {
        $this->listener->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue(null));
    }

    private function sessionAlreadySet()
    {
        $this->request->expects($this->any())
            ->method('getSession')
            ->will($this->returnValue(clone $this->session));
    }

    private function sessionMustBeSet()
    {
        $this->request->expects($this->once())
            ->method('setSession')
            ->with($this->identicalTo($this->session));
    }

    private function sessionMustNotBeSet()
    {
        $this->request->expects($this->never())
            ->method('setSession');
    }

    private function getSession()
    {
        $mock = $this->getMockBuilder(SessionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $mock;
    }
}
