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
use Symfony\Component\HttpKernel\Attribute\MapRateLimit;
use Symfony\Component\HttpKernel\Attribute\RateLimit as RateLimitAttribute;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\MapRateLimitValueResolver;
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

class MapRateLimitValueResolverTest extends TestCase
{
    public function testResolveSkipsOtherTypesUnlessMapped()
    {
        $resolver = new MapRateLimitValueResolver();
        $request = Request::create('/');

        $this->assertSame([], $resolver->resolve($request, new ArgumentMetadata('id', 'int', false, false, null)));

        $this->expectException(\LogicException::class);
        $resolver->resolve($request, new ArgumentMetadata('id', 'int', false, false, null, false, [new MapRateLimit()]));
    }

    public function testMappedVariadicRateLimitIsRejected()
    {
        $resolver = new MapRateLimitValueResolver();

        try {
            $resolver->resolve(Request::create('/'), new ArgumentMetadata('rateLimit', RateLimit::class, true, false, null, true, [new MapRateLimit()]));
            $this->fail('Expected a LogicException for a variadic argument.');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('must not be variadic', $e->getMessage());
        }
    }

    public function testUnmappedRateLimitArgumentIsMappedLikeAnUnfilteredMapping()
    {
        $api = $this->applied(new RateLimit(8, new \DateTimeImmutable('+1 minute'), true, 10), 'api', true);
        $login = $this->applied(new RateLimit(2, new \DateTimeImmutable('+1 minute'), true, 10), 'login', false);

        $this->assertSame([$login->rateLimit], $this->map([$api, $login], new ArgumentMetadata('rateLimit', RateLimit::class, false, false, null)));
    }

    public function testUnmappedNullableOrDefaultRateLimitArgumentFallsBack()
    {
        $default = new RateLimit(1, new \DateTimeImmutable('+1 minute'), true, 1);

        $this->assertSame([null, $default], $this->map([], new ArgumentMetadata('nullable', RateLimit::class, false, false, null, true), new ArgumentMetadata('withDefault', RateLimit::class, false, true, $default)));
    }

    public function testUnmappedVariadicRateLimitsUseRequestAttributes()
    {
        $request = Request::create('/');
        $first = new RateLimit(4, new \DateTimeImmutable('+1 minute'), true, 5);
        $second = new RateLimit(2, new \DateTimeImmutable('+1 minute'), true, 5);
        $request->attributes->set('limits', [$first, $second]);

        $controller = static fn (RateLimit ...$limits): int => \count($limits);
        $resolver = new ArgumentResolver(null, [new MapRateLimitValueResolver(), ...ArgumentResolver::getDefaultArgumentValueResolvers()]);
        $arguments = $resolver->getArguments($request, $controller);

        $this->assertSame([$first, $second], $arguments);
        $this->assertSame(2, $controller(...$arguments));
    }

    public function testExplicitMappingRejectsUntypedAndUnionArguments()
    {
        $resolver = new MapRateLimitValueResolver();

        foreach ([null, RateLimit::class.'|string'] as $type) {
            try {
                $resolver->resolve(Request::create('/'), new ArgumentMetadata('limit', $type, false, false, null, false, [new MapRateLimit()]));
                $this->fail('Expected a LogicException for the unsupported argument type.');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('must be typed as', $e->getMessage());
            }
        }
    }

    public function testMissingRateLimitUsesDefaultOrNullAndRejectsRequiredArgument()
    {
        $default = new RateLimit(1, new \DateTimeImmutable('+1 minute'), true, 1);

        $this->assertSame([$default], $this->map([], new ArgumentMetadata('withDefault', RateLimit::class, false, true, $default, false, [new MapRateLimit()])));
        $this->assertSame([null], $this->map([], new ArgumentMetadata('nullable', RateLimit::class, false, false, null, true, [new MapRateLimit()])));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Controller::action');
        $this->map([], new ArgumentMetadata('required', RateLimit::class, false, false, null, false, [new MapRateLimit()], 'Controller::action'));
    }

    public function testMapsTheMostRestrictiveMatchingRateLimit()
    {
        $exposed = $this->applied(new RateLimit(8, new \DateTimeImmutable('+1 minute'), true, 10), 'api', true);
        $hidden = $this->applied(new RateLimit(1, new \DateTimeImmutable('+1 minute'), true, 10), 'internal', false);
        $alsoExposed = $this->applied(new RateLimit(3, new \DateTimeImmutable('+1 minute'), true, 10), 'other', true);

        $this->assertSame(
            [$hidden->rateLimit, $alsoExposed->rateLimit],
            $this->map([$exposed, $hidden, $alsoExposed], $this->argument(new MapRateLimit()), $this->argument(new MapRateLimit(exposed: true))),
        );
    }

    public function testMapsHiddenOrNamedRateLimitAndReturnsNullWhenNoneMatches()
    {
        $firstLogin = $this->applied(new RateLimit(8, new \DateTimeImmutable('+1 minute'), true, 10), 'login', true);
        $secondLogin = $this->applied(new RateLimit(4, new \DateTimeImmutable('+1 minute'), true, 10), 'login', false);

        $this->assertSame(
            [$secondLogin->rateLimit, $secondLogin->rateLimit, $secondLogin->rateLimit, null],
            $this->map(
                [$firstLogin, $secondLogin],
                $this->argument(new MapRateLimit(exposed: false)),
                $this->argument(new MapRateLimit(limiter: 'login', exposed: false)),
                $this->argument(new MapRateLimit(limiter: 'login')),
                $this->argument(new MapRateLimit(limiter: 'missing')),
            ),
        );
    }

    public function testMapsByRemainingCallsRatherThanRemainingTokens()
    {
        $cheap = $this->applied(new RateLimit(20, new \DateTimeImmutable('+1 minute'), true, 100), 'cheap', false);
        $expensive = $this->applied(new RateLimit(90, new \DateTimeImmutable('+1 minute'), true, 100), 'expensive', false, 10);

        $this->assertSame([$expensive->rateLimit], $this->map([$cheap, $expensive], $this->argument(new MapRateLimit())));
    }

    public function testMapsNoLimitRateLimitWithoutResetTime()
    {
        $unlimited = $this->applied(new RateLimit(\PHP_INT_MAX, new \DateTimeImmutable('+1 minute'), true, \PHP_INT_MAX), 'unlimited', true);

        $this->assertSame(
            [$unlimited->rateLimit, $unlimited->rateLimit],
            $this->map([$unlimited], $this->argument(new MapRateLimit()), $this->argument(new MapRateLimit(exposed: true))),
        );
        $this->assertNull($unlimited->rateLimit->getResetAt());
    }

    public function testFilteringByNameAndExposureRequiresBothToMatch()
    {
        $namedHidden = $this->applied(new RateLimit(1, new \DateTimeImmutable('+1 minute'), true, 10), 'api', false);
        $otherExposed = $this->applied(new RateLimit(2, new \DateTimeImmutable('+1 minute'), true, 10), 'other', true);

        $this->assertSame([null], $this->map([$namedHidden, $otherExposed], $this->argument(new MapRateLimit(limiter: 'api', exposed: true))));
    }

    public function testPositionalLimiterNameSelectsMatchingRateLimit()
    {
        $api = $this->applied(new RateLimit(8, new \DateTimeImmutable('+1 minute'), true, 10), 'api', true);
        $other = $this->applied(new RateLimit(1, new \DateTimeImmutable('+1 minute'), true, 10), 'other', true);

        $this->assertSame([$api->rateLimit], $this->map([$api, $other], $this->argument(new MapRateLimit('api'))));
    }

    public function testArgumentsAreMappedOncePerRequest()
    {
        $resolver = new MapRateLimitValueResolver();
        $request = Request::create('/');
        $argument = $this->argument(new MapRateLimit());
        $request->attributes->set(RateLimitAttributeListener::RATE_LIMIT_ATTRIBUTE, [$this->applied(new RateLimit(1, new \DateTimeImmutable('+1 minute'), true, 10), 'api', true)]);

        $resolver->resolve($request, $argument);
        $event = new ControllerArgumentsEvent($this->createStub(HttpKernelInterface::class), static fn () => null, [$argument], $request, null);
        $resolver->onKernelControllerArguments($event);
        $this->assertInstanceOf(RateLimit::class, $event->getArguments()[0]);

        $event = new ControllerArgumentsEvent($this->createStub(HttpKernelInterface::class), static fn () => null, [$argument], $request, null);
        $resolver->onKernelControllerArguments($event);
        $this->assertSame($argument, $event->getArguments()[0]);
    }

    public function testUnmappedArgumentReceivesTheRateLimit()
    {
        $this->assertSame('4', $this->handle($this->createKernel(), 'unmapped')->getContent());
    }

    public function testMapsAnImmediateRateLimit()
    {
        $kernel = $this->createKernel();

        $this->assertSame('4', $this->handle($kernel, 'immediate')->getContent());
        $this->assertSame('3', $this->handle($kernel, 'immediate')->getContent());
    }

    public function testMappingWaitsForAttributesThatReadTheArguments()
    {
        $kernel = $this->createKernel(static function (ControllerArgumentsEvent $event): void {
            self::assertInstanceOf(ArgumentMetadata::class, $event->getNamedArguments()['rateLimit']);
        });

        $this->assertSame('1', $this->handle($kernel, 'mixed')->getContent());
    }

    public function testMappingSelectsTheLimiterByName()
    {
        $this->assertSame('3', $this->handle($this->createKernel(), 'sameName')->getContent());
    }

    public function testExposureFilterExcludesUnrelatedLimits()
    {
        $this->assertSame('4', $this->handle($this->createKernel(), 'exposed')->getContent());
    }

    public function testSkippedLimitsAreNotMapped()
    {
        $this->assertSame('4', $this->handle($this->createKernel(), 'skipped')->getContent());
    }

    public function testConditionCanLeaveAnOptionalMappingEmpty()
    {
        $this->assertSame('none', $this->handle($this->createKernel(), 'optional')->getContent());
    }

    public function testMissingRateLimitRejectsRequiredMapping()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no matching rate limit was applied');

        $this->handle($this->createKernel(), 'required');
    }

    public function testMissingRateLimitUsesTheDeclaredDefault()
    {
        $this->assertSame('7', $this->handle($this->createKernel(), 'withDefault')->getContent());
    }

    public function testRejectedLimitNeverInvokesTheController()
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
        $resolver = new MapRateLimitValueResolver();
        $dispatcher->addSubscriber($resolver);
        if ($onArguments) {
            $dispatcher->addListener(KernelEvents::CONTROLLER_ARGUMENTS, $onArguments);
        }

        return new HttpKernel($dispatcher, new ControllerResolver(), null, new ArgumentResolver(null, [$resolver, ...ArgumentResolver::getDefaultArgumentValueResolvers()], new ServiceLocator([MapRateLimitValueResolver::class => static fn () => $resolver])));
    }

    private function handle(HttpKernel $kernel, string $method): Response
    {
        $request = Request::create('/');
        $request->attributes->set('_controller', [new MappedRateLimitController(), $method]);
        $request->attributes->set('id', 'shared');

        return $kernel->handle($request);
    }

    private function argument(MapRateLimit $mapping): ArgumentMetadata
    {
        static $i = 0;

        return new ArgumentMetadata('rateLimit'.++$i, RateLimit::class, false, false, null, true, [$mapping]);
    }

    /**
     * @param list<AppliedRateLimit> $applied
     */
    private function map(array $applied, ArgumentMetadata ...$arguments): array
    {
        $resolver = new MapRateLimitValueResolver();
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

    private function applied(RateLimit $rateLimit, string $limiter, bool $exposed, int $tokens = 1): AppliedRateLimit
    {
        return new AppliedRateLimit($rateLimit, $tokens, $limiter, $exposed);
    }
}

class MappedRateLimitController
{
    #[RateLimitAttribute('api', key: 'shared', exposeHeaders: true)]
    public function unmapped(RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared', exposeHeaders: true)]
    public function immediate(#[MapRateLimit] RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared')]
    #[RateLimitAttribute('other', key: new Expression('args["id"]'))]
    public function mixed(string $id, #[MapRateLimit] RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared')]
    #[RateLimitAttribute('api', key: new Expression('args["id"]'))]
    public function sameName(string $id, #[MapRateLimit('api')] RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared', exposeHeaders: true)]
    #[RateLimitAttribute('other', key: new Expression('args["id"]'))]
    public function exposed(string $id, #[MapRateLimit(exposed: true)] RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared')]
    #[RateLimitAttribute('other', key: new Expression('args["id"]'), methods: 'POST')]
    #[RateLimitAttribute('other', key: new Expression('args["id"]'), if: false)]
    public function skipped(string $id, #[MapRateLimit] RateLimit $rateLimit): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', if: new Expression('args["id"] == "skip"'))]
    public function optional(string $id, #[MapRateLimit] ?RateLimit $rateLimit = null): Response
    {
        return new Response($rateLimit ? 'applied' : 'none');
    }

    #[RateLimitAttribute('api', if: new Expression('args["id"] == "skip"'))]
    public function required(string $id, #[MapRateLimit] RateLimit $rateLimit): Response
    {
        throw new \LogicException('The required rate limit was not applied.');
    }

    #[RateLimitAttribute('api', if: new Expression('args["id"] == "skip"'))]
    public function withDefault(string $id, #[MapRateLimit] RateLimit $rateLimit = new RateLimit(7, new \DateTimeImmutable('2026-01-01'), true, 10)): Response
    {
        return new Response((string) $rateLimit->getRemainingTokens());
    }

    #[RateLimitAttribute('api', key: 'shared', tokens: 5)]
    #[RateLimitAttribute('api', key: new Expression('args["id"]'))]
    public function rejected(string $id, #[MapRateLimit] RateLimit $rateLimit): Response
    {
        throw new \LogicException('The rejected controller must not be called.');
    }
}
