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

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testDefaultKeyDoesNotDependOnTheMethod()
    {
        $listener = $this->makeListenerCapturingKey($usedKey);

        $keys = [];
        foreach (['GET', 'HEAD', 'POST', 'FOO'] as $method) {
            $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), Request::create('/login', $method, server: ['REMOTE_ADDR' => '1.2.3.4'])));
            $keys[] = $usedKey;
        }

        $this->assertSame(['1.2.3.4~/login'], array_unique($keys));
    }

    #[DataProvider('provideDefaultKeyPathCases')]
    public function testDefaultKeyNormalizesThePercentEncoding(string $path, string $expectedKey)
    {
        $listener = $this->makeListenerCapturingKey($usedKey);
        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), Request::create($path, 'POST', [], [], [], ['REMOTE_ADDR' => '1.2.3.4'])));

        $this->assertSame($expectedKey, $usedKey);
    }

    public static function provideDefaultKeyPathCases(): iterable
    {
        yield 'plain' => ['/login', '1.2.3.4~/login'];
        yield 'encoded letter' => ['/l%6Fgin', '1.2.3.4~/login'];
        yield 'lowercase hex' => ['/l%6fgin', '1.2.3.4~/login'];
        yield 'fully encoded' => ['/%6C%6F%67%69%6E', '1.2.3.4~/login'];
        yield 'encoded slash' => ['/users/a%2fb', '1.2.3.4~/users/a/b'];
        yield 'space and control characters' => ['/users/a%20b%0A%00', '1.2.3.4~/users/a%20b%0A%00'];
        yield 'invalid UTF-8' => ['/users/%ff', '1.2.3.4~/users/%FF'];
        yield 'raw invalid UTF-8' => ["/users/\xff", '1.2.3.4~/users/%FF'];
        yield 'UTF-8' => ['/caf%c3%a9', '1.2.3.4~/caf%C3%A9'];
        yield 'raw UTF-8' => ["/caf\xc3\xa9", '1.2.3.4~/caf%C3%A9'];
        yield 'plus is not a space' => ['/users/a+b', '1.2.3.4~/users/a%2Bb'];
        yield 'decoded only once' => ['/l%256Fgin', '1.2.3.4~/l%256Fgin'];
    }

    #[DataProvider('provideDefaultKeyClientIpCases')]
    public function testDefaultKeyLimitsIpv6ClientsByTheirNetwork(string $ip, string $expectedKey)
    {
        $listener = $this->makeListenerCapturingKey($usedKey);
        $listener->onKernelControllerAttribute($this->makeEvent(new RateLimit('api'), Request::create('/login', server: ['REMOTE_ADDR' => $ip])));

        $this->assertSame($expectedKey, $usedKey);
    }

    public static function provideDefaultKeyClientIpCases(): iterable
    {
        yield 'IPv4' => ['192.0.2.1', '192.0.2.1~/login'];
        yield 'IPv6' => ['2001:db8:1:2::1', '2001:db8:1:2::~/login'];
        yield 'IPv6 in the same /64' => ['2001:db8:1:2:ffff:ffff:ffff:ffff', '2001:db8:1:2::~/login'];
        yield 'IPv6 in another /64' => ['2001:db8:1:3::1', '2001:db8:1:3::~/login'];
        yield 'IPv4-mapped IPv6' => ['::ffff:192.0.2.1', '::ffff:192.0.2.1~/login'];
        yield 'not an IP' => ['unix:', 'unix:~/login'];
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
