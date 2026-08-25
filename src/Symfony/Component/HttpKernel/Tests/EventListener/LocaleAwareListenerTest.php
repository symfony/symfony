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

use PHPUnit\Framework\MockObject\MockObject;
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
    private LocaleAwareListener $listener;
    private MockObject&LocaleAwareInterface $localeAwareService;
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->localeAwareService = $this->createMock(LocaleAwareInterface::class);
        $this->requestStack = new RequestStack();
        $this->listener = new LocaleAwareListener(new \ArrayIterator([$this->localeAwareService]), $this->requestStack);
    }

    public function testLocaleIsSetInOnKernelRequest()
    {
        $this->localeAwareService
            ->expects($this->once())
            ->method('setLocale')
            ->with($this->equalTo('fr'));

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $this->createRequest('fr'), HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($event);
    }

    public function testDefaultLocaleIsUsedOnExceptionsInOnKernelRequest()
    {
        $this->localeAwareService
            ->expects($this->exactly(2))
            ->method('setLocale')
            ->willReturnCallback(function (string $locale): void {
                static $counter = 0;

                if (1 === ++$counter) {
                    throw new \InvalidArgumentException();
                }

                $this->assertSame('en', $locale);
            })
        ;

        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $this->createRequest('fr'), HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($event);
    }

    public function testLocaleIsSetInOnKernelFinishRequestWhenParentRequestExists()
    {
        $this->localeAwareService
            ->expects($this->once())
            ->method('setLocale')
            ->with($this->equalTo('fr'));

        $this->requestStack->push($this->createRequest('fr'));
        $this->requestStack->push($subRequest = $this->createRequest('de'));

        $event = new FinishRequestEvent($this->createStub(HttpKernelInterface::class), $subRequest, HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    public function testLocaleIsSetToDefaultOnKernelFinishRequestWhenParentRequestDoesNotExist()
    {
        $this->localeAwareService
            ->expects($this->once())
            ->method('setLocale')
            ->with($this->equalTo('en'));

        $this->requestStack->push($subRequest = $this->createRequest('de'));

        $event = new FinishRequestEvent($this->createStub(HttpKernelInterface::class), $subRequest, HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    public function testDefaultLocaleIsUsedOnExceptionsInOnKernelFinishRequest()
    {
        $this->localeAwareService
            ->expects($this->exactly(2))
            ->method('setLocale')
            ->willReturnCallback(function (string $locale): void {
                static $counter = 0;

                if (1 === ++$counter) {
                    throw new \InvalidArgumentException();
                }

                $this->assertSame('en', $locale);
            })
        ;

        $this->requestStack->push($this->createRequest('fr'));
        $this->requestStack->push($subRequest = $this->createRequest('de'));

        $event = new FinishRequestEvent($this->createStub(HttpKernelInterface::class), $subRequest, HttpKernelInterface::SUB_REQUEST);
        $this->listener->onKernelFinishRequest($event);
    }

    public function testLocaleSetOutsideOfTheListenerIsRestoredAfterASubRequest()
    {
        $this->localeAwareService->expects($this->never())->method('setLocale');

        $service = new class implements LocaleAwareInterface {
            private string $locale = 'en';

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }
        };
        $listener = new LocaleAwareListener(new \ArrayIterator([$service]), $this->requestStack);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $this->requestStack->push($mainRequest = $this->createRequest('en'));
        $listener->onKernelRequest(new RequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST));

        $service->setLocale('fr');

        $this->requestStack->push($subRequest = $this->createRequest('de'));
        $listener->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));
        $this->assertSame('de', $service->getLocale());

        $listener->onKernelFinishRequest(new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));
        $this->requestStack->pop();

        $this->assertSame('fr', $service->getLocale());
    }

    public function testSubRequestDoesNotFailOnAServiceWithAnUninitializedLocale()
    {
        $this->localeAwareService->expects($this->never())->method('setLocale');

        $service = new class implements LocaleAwareInterface {
            private string $locale;

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }
        };
        $listener = new LocaleAwareListener(new \ArrayIterator([$service]), $this->requestStack);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $this->requestStack->push($mainRequest = $this->createRequest('fr'));
        $this->requestStack->push($subRequest = $this->createRequest('de'));
        $listener->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));

        $this->assertSame('de', $service->getLocale());

        $listener->onKernelFinishRequest(new FinishRequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));
        $this->requestStack->pop();

        $this->assertSame('fr', $service->getLocale());
    }

    public function testSubRequestDoesNotHideAnUnrelatedErrorThrownByGetLocale()
    {
        $this->localeAwareService->expects($this->never())->method('setLocale');

        $service = new class implements LocaleAwareInterface {
            private ?LocaleAwareInterface $inner = null;

            public function setLocale(string $locale): void
            {
            }

            public function getLocale(): string
            {
                return $this->inner->getLocale();
            }
        };
        $listener = new LocaleAwareListener(new \ArrayIterator([$service]), $this->requestStack);
        $kernel = $this->createStub(HttpKernelInterface::class);

        $this->requestStack->push($mainRequest = $this->createRequest('fr'));
        $listener->onKernelRequest(new RequestEvent($kernel, $mainRequest, HttpKernelInterface::MAIN_REQUEST));

        $this->requestStack->push($subRequest = $this->createRequest('de'));

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Call to a member function getLocale() on null');

        $listener->onKernelRequest(new RequestEvent($kernel, $subRequest, HttpKernelInterface::SUB_REQUEST));
    }

    private function createRequest(string $locale): Request
    {
        $request = new Request();
        $request->setLocale($locale);

        return $request;
    }
}
