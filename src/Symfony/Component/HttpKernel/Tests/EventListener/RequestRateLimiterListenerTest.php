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
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\EventListener\RequestRateLimiterListener;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\RateLimiter\RequestRateLimiter;
use Symfony\Component\HttpKernel\Tests\Fixtures\RequestRateLimitAttributeController;
use Symfony\Component\HttpKernel\Tests\Fixtures\RequestRateLimitAttributeMethodsController;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class RequestRateLimiterListenerTest extends TestCase
{
    public function testInvokableControllerWithValidAvalibleRateLimit()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
        ]));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            new RequestRateLimitAttributeController(),
            [],
            new Request(),
            null
        ));
    }

    public function testWithDefaultBehaviorControllerWithValidAvalibleRateLimit()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
        ]));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeController(), 'withDefaultBehavior'],
            [],
            new Request(),
            null
        ));
    }

    public function testNoAttributeSkipsValidation()
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');
        $listener = new RequestRateLimiterListener(new RequestRateLimiter($container, 'foo'));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'noAttribute'],
            [],
            new Request(),
            null
        ));
    }

    public function testWithDefaultBehaviorCheckRequestSucceeds()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
        ]));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withDefaultBehavior'],
            [],
            new Request(),
            null
        ));
    }

    public function testWithDefaultBehaviorCheckThrowsTooManyRequestsHttpException()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test' => new RateLimit(1, new \DateTimeImmutable(), false, 1),
        ]));
        $this->expectException(TooManyRequestsHttpException::class);
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withDefaultBehavior'],
            [],
            new Request(),
            null
        ));
    }

    public function testWithMultipleFactoriesAllAvalible()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test_one' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
            'limiter.test_two' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
        ]));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withMultipleFactories'],
            [],
            new Request(),
            null
        ));
    }

    public function testWithMultipleFactoriesOnlyOneAvalible()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test_one' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
            'limiter.test_two' => new RateLimit(1, new \DateTimeImmutable(), false, 1),
        ]));
        $this->expectException(TooManyRequestsHttpException::class);
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withMultipleFactories'],
            [],
            new Request(),
            null
        ));
    }

    public function testWithInvalidRateLimiterFactory()
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new \stdClass());
        $listener = new RequestRateLimiterListener(new RequestRateLimiter($container, 'foo'));
        $this->expectException(\InvalidArgumentException::class);
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withInvalidService'],
            [],
            new Request(),
            null
        ));
    }

    public function testWithMultipleAttributesAllAvalible()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test_one' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
            'limiter.test_two' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
        ]));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withMultipleFactories'],
            [],
            new Request(),
            null
        ));
    }

    public function testWithMultipleAttributesOnlyOneAvalible()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test_one' => new RateLimit(1, new \DateTimeImmutable(), false, 1),
            'limiter.test_two' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
        ]));
        $this->expectException(TooManyRequestsHttpException::class);
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withMultipleFactories'],
            [],
            new Request(),
            null
        ));
    }

    public function testWithPostOnlyCheckRequestSucceeds()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
        ]));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withPostOnly'],
            [],
            new Request(server: ['REQUEST_METHOD' => 'POST']),
            null
        ));
    }

    public function testWithPostOnlySkipsOnGet()
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');
        $listener = new RequestRateLimiterListener(new RequestRateLimiter($container, 'foo'));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withPostOnly'],
            [],
            new Request(server: ['REQUEST_METHOD' => 'GET']),
            null
        ));
    }

    public function testWithGetAndPostCheckRequestSucceeds()
    {
        $listener = new RequestRateLimiterListener($this->createRequestRateLimiter([
            'limiter.test' => new RateLimit(1, new \DateTimeImmutable(), true, 1),
        ]));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withGetAndPost'],
            [],
            new Request(server: ['REQUEST_METHOD' => 'GET']),
            null
        ));
    }

    public function testWithGetAndPostSkipsOnPut()
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->never())->method('get');
        $listener = new RequestRateLimiterListener(new RequestRateLimiter($container, 'foo'));
        $listener->onKernelControllerArguments(new ControllerArgumentsEvent(
            $this->createMock(HttpKernelInterface::class),
            [new RequestRateLimitAttributeMethodsController(), 'withGetAndPost'],
            [],
            new Request(server: ['REQUEST_METHOD' => 'PUT']),
            null
        ));
    }

    /**
     * @param array<string, RateLimit> $rateLimits
     */
    private function createRequestRateLimiter(array $rateLimits, string $secret = 'foo'): RequestRateLimiter
    {
        $rateLimiterFactories = [];
        foreach ($rateLimits as $key => $value) {
            $limiter = $this->createMock(LimiterInterface::class);
            $limiter->expects($this->once())->method('consume')->willReturn($value);
            $rateLimiterFactory = $this->createMock(RateLimiterFactoryInterface::class);
            $rateLimiterFactory->expects($this->once())->method('create')->willReturn($limiter);
            $rateLimiterFactories[] = [$key, $rateLimiterFactory];
        }
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(\count($rateLimiterFactories)))
            ->method('get')
            ->willReturnMap($rateLimiterFactories)
        ;

        return new RequestRateLimiter($container, $secret);
    }
}
