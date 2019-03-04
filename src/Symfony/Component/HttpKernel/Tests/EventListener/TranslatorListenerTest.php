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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\EventListener\TranslatorListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class TranslatorListenerTest extends TestCase
{
    private $listener;
    private $translator;
    private $requestStack;

    protected function setUp()
    {
        $this->translator = $this->getMockBuilder(LocaleAwareInterface::class)->getMock();
        $this->requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')->getMock();
        $this->listener = new TranslatorListener($this->translator, $this->requestStack);
    }

    public function testLocaleIsSetInOnKernelRequest()
    {
        $this->translator
            ->expects($this->once())
            ->method('setLocale')
            ->with($this->equalTo('fr'));

        $event = new GetResponseEvent($this->createHttpKernel(), $this->createRequest('fr'), HttpKernelInterface::MASTER_REQUEST);
        $this->listener->onKernelRequest($event);
    }

    public function testDefaultLocaleIsUsedOnExceptionsInOnKernelRequest()
    {
        $this->translator
            ->expects($this->at(0))
            ->method('setLocale')
            ->willThrowException(new \InvalidArgumentException());
        $this->translator
            ->expects($this->at(1))
            ->method('setLocale')
            ->with($this->equalTo('en'));

        $event = new GetResponseEvent($this->createHttpKernel(), $this->createRequest('fr'), HttpKernelInterface::MASTER_REQUEST);
        $this->listener->onKernelRequest($event);
    }

    public function testLocaleIsSetInOnKernelFinishRequestWhenParentRequestExists()
    {
        $this->translator
            ->expects($this->once())
            ->method('setLocale')
            ->with($this->equalTo('fr'));

        $this->setMasterRequest($this->createRequest('fr'));
        $event = new FinishRequestEvent($this->createHttpKernel(), $this->createRequest('de'), HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    public function testLocaleIsNotSetInOnKernelFinishRequestWhenParentRequestDoesNotExist()
    {
        $this->translator
            ->expects($this->never())
            ->method('setLocale');

        $event = new FinishRequestEvent($this->createHttpKernel(), $this->createRequest('de'), HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    public function testDefaultLocaleIsUsedOnExceptionsInOnKernelFinishRequest()
    {
        $this->translator
            ->expects($this->at(0))
            ->method('setLocale')
            ->willThrowException(new \InvalidArgumentException());
        $this->translator
            ->expects($this->at(1))
            ->method('setLocale')
            ->with($this->equalTo('en'));

        $this->setMasterRequest($this->createRequest('fr'));
        $event = new FinishRequestEvent($this->createHttpKernel(), $this->createRequest('de'), HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    private function createHttpKernel()
    {
        return $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
    }

    private function createRequest($locale)
    {
        $request = new Request();
        $request->setLocale($locale);

        return $request;
    }

    private function setMasterRequest($request)
    {
        $this->requestStack
            ->expects($this->any())
            ->method('getParentRequest')
            ->will($this->returnValue($request));
    }
}
