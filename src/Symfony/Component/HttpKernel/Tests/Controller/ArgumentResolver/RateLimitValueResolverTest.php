<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolver;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RateLimit as RateLimitAttribute;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RateLimitValueResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerAttributesListener;
use Symfony\Component\HttpKernel\EventListener\RateLimitAttributeListener;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\RateLimiter\AppliedRateLimit;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class RateLimitValueResolverTest extends TestCase
{
    public function testResolveSkipsOtherArguments()
    {
        $resolver = new RateLimitValueResolver();
        $request = Request::create('/');

        $this->assertSame([], $resolver->resolve($request, new ArgumentMetadata('id', 'int', false, false, null)));
        $this->assertSame([], $resolver->resolve($request, new ArgumentMetadata('limit', null, false, false, null)));
        $this->assertSame([], $resolver->resolve($request, new ArgumentMetadata('limits', RateLimit::class, true, false, null)));
    }

    public function testVariadicRateLimitsAreLeftToOtherResolvers()
    {
        $request = Request::create('/');
        $first = new RateLimit(4, new \DateTimeImmutable('+1 minute'), true, 5);
        $second = new RateLimit(2, new \DateTimeImmutable('+1 minute'), true, 5);
        $request->attributes->set('limits', [$first, $second]);

        $controller = static fn (RateLimit ...$limits): int => \count($limits);
        $resolver = new ArgumentResolver(null, [new RateLimitValueResolver(), ...ArgumentResolver::getDefaultArgumentValueResolvers()]);
        $arguments = $resolver->getArguments($request, $controller);

        $this->assertSame([$first, $second], $arguments);
        $this->assertSame(2, $controller(...$arguments));
    }

    public function testResolvesTheRateLimitWithTheFewestRemainingCalls()
    {
        $exposed = $this->applied(new RateLimit(8, new \DateTimeImmutable('+1 minute'), true, 10), true);
        $hidden = $this->applied(new RateLimit(2, new \DateTimeImmutable('+1 minute'), true, 10), false);
        $other = $this->applied(new RateLimit(3, new \DateTimeImmutable('+1 minute'), true, 10), true);

        $this->assertSame([$hidden->rateLimit], $this->map([$exposed, $hidden, $other], $this->argument()));
    }

    public function testResolvesByRemainingCallsRatherThanRemainingTokens()
    {
        $cheap = $this->applied(new RateLimit(20, new \DateTimeImmutable('+1 minute'), true, 100), false);
        $expensive = $this->applied(new RateLimit(90, new \DateTimeImmutable('+1 minute'), true, 100), false, 10);

        $this->assertSame([$expensive->rateLimit], $this->map([$cheap, $expensive], $this->argument()));
    }

    public function testResolvesARateLimitWithoutResetTime()
    {
        $unlimited = $this->applied(new RateLimit(\PHP_INT_MAX, new \DateTimeImmutable('+1 minute'), true, \PHP_INT_MAX), true);

        $this->assertSame([$unlimited->rateLimit], $this->map([$unlimited], $this->argument()));
        $this->assertNull($unlimited->rateLimit->getResetAt());
    }

    public function testMissingRateLimitUsesTheDefaultValueOrNull()
    {
        $default = new RateLimit(1, new \DateTimeImmutable('+1 minute'), true, 1);

        $this->assertSame([null, $default], $this->map([], new ArgumentMetadata('nullable', RateLimit::class, false, false, null, true), new ArgumentMetadata('withDefault', RateLimit::class, false, true, $default)));
    }

    public function testUnrelatedRequestAttributeIsIgnored()
    {
        $resolver = new RateLimitValueResolver();
        $request = Request::create('/');
        $request->attributes->set(RateLimitAttributeListener::RATE_LIMIT_ATTRIBUTE, 'a route default');
        $argument = $this->argument();

        $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($this->createStub(HttpKernelInterface::class), static fn () => null, [$argument], $request, null);
        $resolver->onKernelControllerArguments($event);

        $this->assertSame([null], $event->getArguments());
    }

    public function testMissingRateLimitThrowsForANonNullableArgument()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot resolve the "$required" argument of "Controller::action()": no "#[RateLimit]" attribute applies to the request, either because the controller has none or because their "$methods" or "$if" options exclude the request. Make the argument nullable if this is expected.');

        $this->map([], new ArgumentMetadata('required', RateLimit::class, false, false, null, false, [], 'Controller::action'));
    }

    public function testArgumentsAreResolvedOncePerRequest()
    {
        $resolver = new RateLimitValueResolver();
        $request = Request::create('/');
        $argument = $this->argument();
        $request->attributes->set(RateLimitAttributeListener::RATE_LIMIT_ATTRIBUTE, [$this->applied(new RateLimit(1, new \DateTimeImmutable('+1 minute'), true, 10), true)]);

        $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($this->createStub(HttpKernelInterface::class), static fn () => null, [$argument], $request, null);
        $resolver->onKernelControllerArguments($event);
        $this->assertInstanceOf(RateLimit::class, $event->getArguments()[0]);

        $event = new ControllerArgumentsEvent($this->createStub(HttpKernelInterface::class), static fn () => null, [$argument], $request, null);
        $resolver->onKernelControllerArguments($event);
        $this->assertSame($argument, $event->getArguments()[0]);
    }

    public function testKernelResolvesTheRateLimitArgument()
    {
        $kernel = $this->createKernel();

        $this->assertSame('4', $this->handle($kernel, 'single')->getContent());
        $this->assertSame('3', $this->handle($kernel, 'single')->getContent());
    }

    public function testKernelResolvesTheRateLimitArgumentOnceTheAttributesThatReadTheArgumentsAreApplied()
    {
        $kernel = $this->createKernel(static function (ControllerArgumentsEvent $event): void {
            self::assertInstanceOf(ArgumentMetadata::class, $event->getNamedArguments()['rateLimit']);
        });

        $this->assertSame('1', $this->handle($kernel, 'several')->getContent());
    }

    public function testSkippedAttributesAreIgnored()
    {
        $this->assertSame('4', $this->handle($this->createKernel(), 'skipped')->getContent());
    }

    public function testNullableArgumentIsNullWhenNoAttributeApplies()
    {
        $this->assertSame('none', $this->handle($this->createKernel(), 'optional')->getContent());
    }

    public function testArgumentGetsItsDefaultValueWhenNoAttributeApplies()
    {
        $this->assertSame('7', $this->handle($this->createKernel(), 'withDefault')->getContent());
    }

    public function testArgumentThrowsWhenNoAttributeApplies()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot resolve the "$rateLimit" argument of "'.RateLimitedController::class.'::required()": no "#[RateLimit]" attribute applies to the request');

        $this->handle($this->createKernel(), 'required');
    }

    public function testRejectedRequestNeverCallsTheController()
    {
        $this->expectException(TooManyRequestsHttpException::class);

        $this->handle($this->createKernel(), 'rejected');
    }

    private function createKernel(?\Closure $onArguments = null): HttpKernel
    {
        $factories = [];
        foreach (['api' => 5, 'other' => 2] as $name => $limit) {
            $factories[$name] = new RateLimiterFactory(['id' => $name, 'policy' => 'fixed_window', 'limit' => $limit, 'interval' => '1 minute'], new InMemoryStorage());
        }
        $locator = new ServiceLocator(array_map(static fn ($factory) => static fn () => $factory, $factories));
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new ControllerAttributesListener([
            KernelEvents::CONTROLLER => [RateLimitAttribute::class => true],
            KernelEvents::CONTROLLER_ARGUMENTS => [RateLimitAttribute::class => true],
        ]));
        $dispatcher->addSubscriber(new RateLimitAttributeListener($locator));
        $resolver = new RateLimitValueResolver();
        $dispatcher->addSubscriber($resolver);
        if ($onArguments) {
            $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, $onArguments);
        }

        return new HttpKernel($dispatcher, new ControllerResolver(), null, new ArgumentResolver(null, [$resolver, ...ArgumentResolver::getDefaultArgumentValueResolvers()]));
    }

    private function handle(HttpKernel $kernel, string $method): Response
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', [new RateLimitedController(), $method]);
        $request->attributes->set('id', 'shared');

        return $kernel->handle($request);
    }

    private function argument(): ArgumentMetadata
    {
        return new ArgumentMetadata('rateLimit', RateLimit::class, false, false, null, true);
    }

    /**
     * @param list<AppliedRateLimit> $applied
     */
    private function map(array $applied, ArgumentMetadata ...$arguments): array
    {
        $resolver = new RateLimitValueResolver();
        $request = Request::create('/');
        $request->attributes->set(RateLimitAttributeListener::RATE_LIMIT_ATTRIBUTE, $applied);

        $placeholders = [];
        foreach ($arguments as $argument) {
            $placeholders[] = $resolver->resolve($request, $argument)[0];
        }

        $event = new ControllerArgumentsEvent($this->createStub(HttpKernelInterface::class), static fn () => null, $placeholders, $request, null);
        $resolver->onKernelControllerArguments($event);

        return $event->getArguments();
    }

    private function applied(RateLimit $rateLimit, bool $exposed, int $tokens = 1): AppliedRateLimit
    {
        return new AppliedRateLimit($rateLimit, $tokens, $exposed);
    }
}

class RateLimitedController
{
    #[RateLimitAttribute('api', key: 'shared', exposeHeaders: true)]
    public function single(RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared')]
    #[RateLimitAttribute('other', key: new Expression('args["id"]'))]
    public function several(string $id, RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared')]
    #[RateLimitAttribute('other', key: new Expression('args["id"]'), methods: 'POST')]
    #[RateLimitAttribute('other', key: new Expression('args["id"]'), if: false)]
    public function skipped(string $id, RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', if: new Expression('args["id"] == "skip"'))]
    public function optional(string $id, ?RateLimit $rateLimit): Response
    {
        return new Response($rateLimit ? 'applied' : 'none');
    }

    #[RateLimitAttribute('api', if: new Expression('args["id"] == "skip"'))]
    public function required(string $id, RateLimit $rateLimit): Response
    {
        throw new \LogicException('The required rate limit was not applied.');
    }

    #[RateLimitAttribute('api', if: new Expression('args["id"] == "skip"'))]
    public function withDefault(string $id, RateLimit $rateLimit = new RateLimit(7, new \DateTimeImmutable('2026-01-01'), true, 10)): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared', tokens: 5)]
    #[RateLimitAttribute('api', key: new Expression('args["id"]'))]
    public function rejected(string $id, RateLimit $rateLimit): Response
    {
        throw new \LogicException('The rejected controller must not be called.');
    }
}
