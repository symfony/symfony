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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\LocaleAwareListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

class LocaleAwareListenerTest extends TestCase
{
    private $listener;
    private $localeAwareService;
    private $requestStack;

    protected function setUp(): void
    {
        $this->localeAwareService = self::createMock(LocaleAwareInterface::class);
        $this->requestStack = new RequestStack();
        $this->listener = new LocaleAwareListener(new \ArrayIterator([$this->localeAwareService]), $this->requestStack);
    }

    public function testLocaleIsSetInOnKernelRequest()
    {
        $this->localeAwareService
            ->expects(self::once())
            ->method('setLocale')
            ->with(self::equalTo('fr'));

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $this->createRequest('fr'), HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($event);
    }

    public function testDefaultLocaleIsUsedOnExceptionsInOnKernelRequest()
    {
        $this->localeAwareService
            ->expects(self::exactly(2))
            ->method('setLocale')
            ->withConsecutive(
                [self::anything()],
                ['en']
            )
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \InvalidArgumentException())
            );

        $event = new RequestEvent(self::createMock(HttpKernelInterface::class), $this->createRequest('fr'), HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($event);
    }

    public function testLocaleIsSetInOnKernelFinishRequestWhenParentRequestExists()
    {
        $this->localeAwareService
            ->expects(self::once())
            ->method('setLocale')
            ->with(self::equalTo('fr'));

        $this->requestStack->push($this->createRequest('fr'));
        $this->requestStack->push($subRequest = $this->createRequest('de'));

        $event = new FinishRequestEvent(self::createMock(HttpKernelInterface::class), $subRequest, HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    public function testLocaleIsSetToDefaultOnKernelFinishRequestWhenParentRequestDoesNotExist()
    {
        $this->localeAwareService
            ->expects(self::once())
            ->method('setLocale')
            ->with(self::equalTo('en'));

        $this->requestStack->push($subRequest = $this->createRequest('de'));

        $event = new FinishRequestEvent(self::createMock(HttpKernelInterface::class), $subRequest, HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    public function testDefaultLocaleIsUsedOnExceptionsInOnKernelFinishRequest()
    {
        $this->localeAwareService
            ->expects(self::exactly(2))
            ->method('setLocale')
            ->withConsecutive(
                [self::anything()],
                ['en']
            )
            ->willReturnOnConsecutiveCalls(
                self::throwException(new \InvalidArgumentException())
            );

        $this->requestStack->push($this->createRequest('fr'));
        $this->requestStack->push($subRequest = $this->createRequest('de'));

        $event = new FinishRequestEvent(self::createMock(HttpKernelInterface::class), $subRequest, HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    private function createRequest($locale)
    {
        $request = new Request();
        $request->setLocale($locale);

        return $request;
    }
}
