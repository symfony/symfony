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
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit as RateLimitResult;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

class RateLimitAttributeListenerTest extends TestCase
{
    private function makeListener(bool $accept = true): RateLimitAttributeListener
    {
        $result = new RateLimitResult($accept ? 4 : 0, new \DateTimeImmutable('+1 minute'), $accept, 5);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($result);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        return new RateLimitAttributeListener($locator);
    }

    private function makeListenerCapturingKey(?string &$usedKey): RateLimitAttributeListener
    {
        $result = new RateLimitResult(4, new \DateTimeImmutable('+1 minute'), true, 5);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($result);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturnCallback(static function (string $key) use ($limiter, &$usedKey) {
            $usedKey = $key;

            return $limiter;
        });

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        return new RateLimitAttributeListener($locator);
    }

    private function makeEvent(RateLimit $attribute, Request $request, ?ExpressionLanguage $el = null): ControllerAttributeEvent
    {
        return new ControllerAttributeEvent($attribute, new ControllerArgumentsEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn () => null,
            [],
            $request,
            null,
        ), $el);
    }

    public function testAccepted()
    {
        $this->makeListener()->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), Request::create('/')));
        $this->addToAssertionCount(1);
    }

    public function testRejectedThrowsWith429AndRetryAfter()
    {
        try {
            $this->makeListener(false)->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), Request::create('/')));
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException $e) {
            $this->assertArrayHasKey('Retry-After', $e->getHeaders());
        }
    }

    public function testExpressionKey()
    {
        $listener = $this->makeListenerCapturingKey($usedKey);
        $request = Request::create('/');
        $request->server->set('REMOTE_ADDR', '1.2.3.4');

        $listener->onKernelControllerAttribute($this->makeEvent(
            new RateLimit('api', key: new Expression('request.getClientIp()')),
            $request,
            new ExpressionLanguage(),
        ));

        $this->assertSame('1.2.3.4', $usedKey);
    }

    public function testClosureKey()
    {
        $listener = $this->makeListenerCapturingKey($usedKey);
        $listener->onKernelControllerAttribute($this->makeEvent(
            new RateLimit('api', key: static fn ($args, Request $request) => $request->getClientIp()),
            Request::create('/', server: ['REMOTE_ADDR' => '5.6.7.8']),
        ));

        $this->assertSame('5.6.7.8', $usedKey);
    }

    public function testNonStringKeyThrows()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('must evaluate to a string, "int" given');

        $this->makeListener()->onKernelControllerAttribute($this->makeEvent(
            new RateLimit('api', key: static fn () => 42),
            Request::create('/'),
        ));
    }

    public function testMethodFilterSkipsNonMatchingMethod()
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);
        $factory->expects($this->never())->method('create');

        $locator = $this->createStub(ServiceProviderInterface::class);
        $locator->method('has')->willReturn(true);
        $locator->method('get')->willReturn($factory);
        $locator->method('getProvidedServices')->willReturn(['api' => RateLimiterFactoryInterface::class]);

        $listener = new RateLimitAttributeListener($locator);
        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api', methods: ['POST']), Request::create('/', 'GET')));
    }
}
