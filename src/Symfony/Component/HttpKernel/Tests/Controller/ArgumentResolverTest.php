<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Exception\NearMissValueResolverException;
use Symfony\Component\HttpKernel\Exception\ResolverNotFoundException;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingRequest;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\ExtendingSession;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\NullableController;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\VariadicController;

class ArgumentResolverTest extends TestCase
{
    public static function getResolver(array $chainableResolvers = [], ?array $namedResolvers = null): ArgumentResolver
    {
        if (null !== $namedResolvers) {
            $namedResolvers = new ServiceLocator(array_map(static fn ($resolver) => static fn () => $resolver, $namedResolvers));
        }

        return new ArgumentResolver(new ArgumentMetadataFactory(), $chainableResolvers, $namedResolvers);
    }

    public function testDefaultState(): void
    {
        $this->assertEquals(self::getResolver(), new ArgumentResolver());
        $this->assertNotEquals(self::getResolver(), new ArgumentResolver(null, [new RequestAttributeValueResolver()]));
    }

    public function testGetArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = [new ArgumentResolverTestController(), 'controllerWithFoo'];

        $this->assertEquals(['foo'], self::getResolver()->getArguments($request, $controller), '->getArguments() returns an array of arguments for the controller method');
    }

    public function testGetArgumentsReturnsEmptyArrayWhenNoArguments(): void
    {
        $request = Request::create('/');
        $controller = [new ArgumentResolverTestController(), 'controllerWithoutArguments'];

        $this->assertEquals([], self::getResolver()->getArguments($request, $controller), '->getArguments() returns an empty array if the method takes no arguments');
    }

    public function testGetArgumentsUsesDefaultValue(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = [new ArgumentResolverTestController(), 'controllerWithFooAndDefaultBar'];

        $this->assertEquals(['foo', null], self::getResolver()->getArguments($request, $controller), '->getArguments() uses default values if present');
    }

    public function testGetArgumentsOverrideDefaultValueByRequestAttribute(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'bar');
        $controller = [new ArgumentResolverTestController(), 'controllerWithFooAndDefaultBar'];

        $this->assertEquals(['foo', 'bar'], self::getResolver()->getArguments($request, $controller), '->getArguments() overrides default values if provided in the request attributes');
    }

    public function testGetArgumentsFromClosure(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static function ($foo): void {};

        $this->assertEquals(['foo'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetArgumentsUsesDefaultValueFromClosure(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = static function ($foo, $bar = 'bar'): void {};

        $this->assertEquals(['foo', 'bar'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetArgumentsFromInvokableObject(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $controller = new ArgumentResolverTestController();

        $this->assertEquals(['foo', null], self::getResolver()->getArguments($request, $controller));

        // Test default bar overridden by request attribute
        $request->attributes->set('bar', 'bar');

        $this->assertEquals(['foo', 'bar'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetArgumentsFromFunctionName(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = __NAMESPACE__.'\controller_function';

        $this->assertEquals(['foo', 'foobar'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetArgumentsFailsOnUnresolvedValue(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('foobar', 'foobar');
        $controller = [new ArgumentResolverTestController(), 'controllerWithFooBarFoobar'];

        try {
            self::getResolver()->getArguments($request, $controller);
            $this->fail('->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e, '->getArguments() throws a \RuntimeException exception if it cannot determine the argument value');
        }
    }

    public function testGetArgumentsInjectsRequest(): void
    {
        $request = Request::create('/');
        $controller = [new ArgumentResolverTestController(), 'controllerWithRequest'];

        $this->assertEquals([$request], self::getResolver()->getArguments($request, $controller), '->getArguments() injects the request');
    }

    public function testGetArgumentsInjectsExtendingRequest(): void
    {
        $request = ExtendingRequest::create('/');
        $controller = [new ArgumentResolverTestController(), 'controllerWithExtendingRequest'];

        $this->assertEquals([$request], self::getResolver()->getArguments($request, $controller), '->getArguments() injects the request when extended');
    }

    public function testGetVariadicArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', ['foo', 'bar']);
        $controller = [new VariadicController(), 'action'];

        $this->assertEquals(['foo', 'foo', 'bar'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetVariadicArgumentsWithoutArrayInRequest(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', 'foo');
        $controller = [new VariadicController(), 'action'];

        self::getResolver()->getArguments($request, $controller);
    }

    public function testIfExceptionIsThrownWhenMissingAnArgument(): void
    {
        $request = Request::create('/');
        $controller = (new ArgumentResolverTestController())->controllerWithFoo(...);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Controller "'.ArgumentResolverTestController::class.'::controllerWithFoo" requires the "$foo" argument that could not be resolved. Either the argument is nullable and no null value has been provided, no default value has been provided or there is a non-optional argument after this one.');
        self::getResolver()->getArguments($request, $controller);
    }

    public function testGetNullableArguments(): void
    {
        $request = Request::create('/');
        $request->attributes->set('foo', 'foo');
        $request->attributes->set('bar', new \stdClass());
        $request->attributes->set('last', 'last');
        $controller = [new NullableController(), 'action'];

        $this->assertEquals(['foo', new \stdClass(), 'value', 'last'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetNullableArgumentsWithDefaults(): void
    {
        $request = Request::create('/');
        $request->attributes->set('last', 'last');
        $controller = [new NullableController(), 'action'];

        $this->assertEquals([null, null, 'value', 'last'], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetSessionArguments(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = (new ArgumentResolverTestController())->controllerWithSession(...);

        $this->assertEquals([$session], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithExtendedSession(): void
    {
        $session = new ExtendingSession(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = (new ArgumentResolverTestController())->controllerWithExtendingSession(...);

        $this->assertEquals([$session], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetSessionArgumentsWithInterface(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = (new ArgumentResolverTestController())->controllerWithSessionInterface(...);

        $this->assertEquals([$session], self::getResolver()->getArguments($request, $controller));
    }

    public function testGetSessionMissMatchWithInterface(): void
    {
        $this->expectException(\RuntimeException::class);
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = (new ArgumentResolverTestController())->controllerWithExtendingSession(...);

        self::getResolver()->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchWithImplementation(): void
    {
        $this->expectException(\RuntimeException::class);
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);
        $controller = (new ArgumentResolverTestController())->controllerWithExtendingSession(...);

        self::getResolver()->getArguments($request, $controller);
    }

    public function testGetSessionMissMatchOnNull(): void
    {
        $this->expectException(\RuntimeException::class);
        $request = Request::create('/');
        $controller = (new ArgumentResolverTestController())->controllerWithExtendingSession(...);

        self::getResolver()->getArguments($request, $controller);
    }

    public function testTargetedResolver(): void
    {
        $resolver = self::getResolver([], [DefaultValueResolver::class => new DefaultValueResolver()]);

        $request = Request::create('/');
        $request->attributes->set('foo', 'bar');
        $controller = (new ArgumentResolverTestController())->controllerTargetingResolver(...);

        $this->assertSame([1], $resolver->getArguments($request, $controller));
    }

    public function testTargetedResolverWithDefaultValue(): void
    {
        $resolver = self::getResolver([], [TestEntityValueResolver::class => new TestEntityValueResolver()]);

        $request = Request::create('/');
        $controller = (new ArgumentResolverTestController())->controllerTargetingResolverWithDefaultValue(...);

        /** @var Post[] $arguments */
        $arguments = $resolver->getArguments($request, $controller);

        $this->assertCount(1, $arguments);
        $this->assertSame('Default', $arguments[0]->title);
    }

    public function testTargetedResolverWithNullableValue(): void
    {
        $resolver = self::getResolver([], [TestEntityValueResolver::class => new TestEntityValueResolver()]);

        $request = Request::create('/');
        $controller = (new ArgumentResolverTestController())->controllerTargetingResolverWithNullableValue(...);

        $this->assertSame([null], $resolver->getArguments($request, $controller));
    }

    public function testTargetedResolverWithRequestAttributeValue(): void
    {
        $resolver = self::getResolver([], [TestEntityValueResolver::class => new TestEntityValueResolver()]);

        $request = Request::create('/');
        $request->attributes->set('foo', $object = new Post('Random '.time()));
        $controller = (new ArgumentResolverTestController())->controllerTargetingResolverWithTestEntity(...);

        $this->assertSame([$object], $resolver->getArguments($request, $controller));
    }

    public function testDisabledResolver(): void
    {
        $resolver = self::getResolver(namedResolvers: []);

        $request = Request::create('/');
        $request->attributes->set('foo', 'bar');
        $controller = (new ArgumentResolverTestController())->controllerDisablingResolver(...);

        $this->assertSame([1], $resolver->getArguments($request, $controller));
    }

    public function testManyTargetedResolvers(): void
    {
        $resolver = self::getResolver(namedResolvers: []);

        $request = Request::create('/');
        $controller = (new ArgumentResolverTestController())->controllerTargetingManyResolvers(...);

        $this->expectException(\LogicException::class);
        $resolver->getArguments($request, $controller);
    }

    public function testUnknownTargetedResolver(): void
    {
        $resolver = self::getResolver(namedResolvers: []);

        $request = Request::create('/');
        $controller = (new ArgumentResolverTestController())->controllerTargetingUnknownResolver(...);

        $this->expectException(ResolverNotFoundException::class);
        $resolver->getArguments($request, $controller);
    }

    public function testResolversChainCompletionWhenResolverThrowsSpecialException(): void
    {
        $failingValueResolver = new class implements ValueResolverInterface {
            public function resolve(Request $request, ArgumentMetadata $argument): iterable
            {
                throw new NearMissValueResolverException('This resolver throws an exception');
            }
        };
        // Put failing value resolver in the beginning
        $expectedToCallValueResolver = $this->createMock(ValueResolverInterface::class);
        $expectedToCallValueResolver->expects($this->once())->method('resolve')->willReturn([123]);

        $resolver = self::getResolver([$failingValueResolver, ...ArgumentResolver::getDefaultArgumentValueResolvers(), $expectedToCallValueResolver]);
        $request = Request::create('/');
        $controller = [new ArgumentResolverTestController(), 'controllerWithFoo'];

        $actualArguments = $resolver->getArguments($request, $controller);
        self::assertEquals([123], $actualArguments);
    }

    public function testExceptionListSingle(): void
    {
        $failingValueResolverOne = new class implements ValueResolverInterface {
            public function resolve(Request $request, ArgumentMetadata $argument): iterable
            {
                throw new NearMissValueResolverException('Some reason why value could not be resolved.');
            }
        };

        $resolver = self::getResolver([$failingValueResolverOne]);
        $request = Request::create('/');
        $controller = [new ArgumentResolverTestController(), 'controllerWithFoo'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Controller "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolverTestController::controllerWithFoo" requires the "$foo" argument that could not be resolved. Some reason why value could not be resolved.');
        $resolver->getArguments($request, $controller);
    }

    public function testExceptionListMultiple(): void
    {
        $failingValueResolverOne = new class implements ValueResolverInterface {
            public function resolve(Request $request, ArgumentMetadata $argument): iterable
            {
                throw new NearMissValueResolverException('Some reason why value could not be resolved.');
            }
        };
        $failingValueResolverTwo = new class implements ValueResolverInterface {
            public function resolve(Request $request, ArgumentMetadata $argument): iterable
            {
                throw new NearMissValueResolverException('Another reason why value could not be resolved.');
            }
        };

        $resolver = self::getResolver([$failingValueResolverOne, $failingValueResolverTwo]);
        $request = Request::create('/');
        $controller = [new ArgumentResolverTestController(), 'controllerWithFoo'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Controller "Symfony\Component\HttpKernel\Tests\Controller\ArgumentResolverTestController::controllerWithFoo" requires the "$foo" argument that could not be resolved. Possible reasons: 1) Some reason why value could not be resolved. 2) Another reason why value could not be resolved.');
        $resolver->getArguments($request, $controller);
    }
}

class ArgumentResolverTestController
{
    public function __invoke($foo, $bar = null): void
    {
    }

    public function controllerWithFoo($foo): void
    {
    }

    public function controllerWithoutArguments(): void
    {
    }

    public function controllerWithFooAndDefaultBar($foo, $bar = null): void
    {
    }

    public function controllerWithFooBarFoobar($foo, $bar, $foobar): void
    {
    }

    public function controllerWithRequest(Request $request): void
    {
    }

    public function controllerWithExtendingRequest(ExtendingRequest $request): void
    {
    }

    public function controllerWithSession(Session $session): void
    {
    }

    public function controllerWithSessionInterface(SessionInterface $session): void
    {
    }

    public function controllerWithExtendingSession(ExtendingSession $session): void
    {
    }

    public function controllerTargetingResolver(#[ValueResolver(DefaultValueResolver::class)] int $foo = 1): void
    {
    }

    public function controllerTargetingResolverWithDefaultValue(#[ValueResolver(TestEntityValueResolver::class)] Post $foo = new Post('Default')): void
    {
    }

    public function controllerTargetingResolverWithNullableValue(#[ValueResolver(TestEntityValueResolver::class)] ?Post $foo): void
    {
    }

    public function controllerTargetingResolverWithTestEntity(#[ValueResolver(TestEntityValueResolver::class)] Post $foo): void
    {
    }

    public function controllerDisablingResolver(#[ValueResolver(RequestAttributeValueResolver::class, disabled: true)] int $foo = 1): void
    {
    }

    public function controllerTargetingManyResolvers(
        #[ValueResolver(RequestAttributeValueResolver::class)]
        #[ValueResolver(DefaultValueResolver::class)]
        int $foo,
    ): void {
    }

    public function controllerTargetingUnknownResolver(
        #[ValueResolver('foo')]
        int $bar,
    ): void {
    }
}

function controller_function($foo, $foobar): void
{
}

class TestEntityValueResolver implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        return Post::class === $argument->getType() && $request->request->has('title')
            ? [new Post($request->request->get('title'))]
            : [];
    }
}

class Post
{
    public function __construct(
        public readonly string $title,
    ) {
    }
}
