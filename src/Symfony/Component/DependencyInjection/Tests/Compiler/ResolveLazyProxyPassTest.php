<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\LazyProxyArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveLazyProxyPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

require_once __DIR__.'/../Fixtures/includes/autowiring_classes.php';

class ResolveLazyProxyPassTest extends TestCase
{
    public function testResolveLazyProxyArgument()
    {
        $container = new ContainerBuilder();
        $container->register('a', A::class);
        $container->register('foo', Foo::class)
            ->addArgument(new LazyProxyArgument(new Reference('a')));

        (new ResolveLazyProxyPass())->process($container);

        $argument = $container->getDefinition('foo')->getArgument(0);
        self::assertInstanceOf(LazyProxyArgument::class, $argument);

        $lazyId = (string) $argument->getValues()[2];
        self::assertStringStartsWith('.lazy.a.', $lazyId);
        self::assertSame(A::class, $container->getDefinition($lazyId)->getClass());
    }

    public function testResolveLazyProxyArgumentWithExplicitInterfaces()
    {
        $container = new ContainerBuilder();
        $container->register('final_impl', FinalLazyProxyImplementation::class);
        $container->register('foo', Foo::class)
            ->addArgument(new LazyProxyArgument(new Reference('final_impl'), LazyProxyTestInterface::class));

        (new ResolveLazyProxyPass())->process($container);

        $lazyId = (string) $container->getDefinition('foo')->getArgument(0)->getValues()[2];
        self::assertStringStartsWith('.lazy.final_impl.', $lazyId);
        self::assertSame(FinalLazyProxyImplementation::class, $container->getDefinition($lazyId)->getClass());
        self::assertSame([['interface' => LazyProxyTestInterface::class]], $container->getDefinition($lazyId)->getTag('proxy'));
    }

    public function testResolveLazyProxyArgumentWithIntersectionType()
    {
        $container = new ContainerBuilder();
        $container->register('a', A::class);
        $container->register('foo', Foo::class)
            ->addArgument(new LazyProxyArgument(new TypedReference('a', AInterface::class.'&'.LazyProxyTestInterface::class)));

        (new ResolveLazyProxyPass())->process($container);

        $lazyId = (string) $container->getDefinition('foo')->getArgument(0)->getValues()[2];
        self::assertStringStartsWith('.lazy.a.', $lazyId);
        self::assertSame('object', $container->getDefinition($lazyId)->getClass());
        self::assertSame([
            ['interface' => AInterface::class],
            ['interface' => LazyProxyTestInterface::class],
        ], $container->getDefinition($lazyId)->getTag('proxy'));
    }

    public function testAlreadyLazyTargetIsNotProxied()
    {
        $container = new ContainerBuilder();
        $container->register('a', A::class)->setLazy(true);
        $container->register('foo', Foo::class)
            ->addArgument(new LazyProxyArgument($reference = new TypedReference('a', A::class)));

        (new ResolveLazyProxyPass())->process($container);

        self::assertSame($reference, $container->getDefinition('foo')->getArgument(0));

        foreach ($container->getDefinitions() as $id => $definition) {
            self::assertStringStartsNotWith('.lazy.', $id);
        }
    }

    public function testUnionTypeThrows()
    {
        $container = new ContainerBuilder();
        $container->register('a', A::class);
        $container->register('foo', Foo::class)
            ->addArgument(new LazyProxyArgument(new TypedReference('a', A::class.'|'.Foo::class)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('Cannot lazily proxy union type "%s|%s" for service "foo"; configure the interface(s) that should be proxied instead.', A::class, Foo::class));

        (new ResolveLazyProxyPass())->process($container);
    }

    public function testReferenceToMissingServiceThrows()
    {
        $container = new ContainerBuilder();
        $container->register('foo', Foo::class)
            ->addArgument(new LazyProxyArgument(new Reference('missing')));

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('The service "foo" has a dependency on a non-existent service "missing".');

        (new ResolveLazyProxyPass())->process($container);
    }

    #[TestWith([ContainerInterface::IGNORE_ON_INVALID_REFERENCE], 'ignore on invalid')]
    #[TestWith([ContainerInterface::NULL_ON_INVALID_REFERENCE], 'null on invalid')]
    public function testOptionalReferenceToMissingServiceIsLeftUntouched(int $invalidBehavior)
    {
        $container = new ContainerBuilder();
        $container->register('foo', Foo::class)
            ->addArgument(new LazyProxyArgument($reference = new Reference('missing', $invalidBehavior)));

        (new ResolveLazyProxyPass())->process($container);

        self::assertSame($reference, $container->getDefinition('foo')->getArgument(0));

        foreach ($container->getDefinitions() as $id => $definition) {
            self::assertStringStartsNotWith('.lazy.', $id);
        }
    }

    #[TestWith([ContainerInterface::IGNORE_ON_INVALID_REFERENCE], 'ignore on invalid')]
    #[TestWith([ContainerInterface::NULL_ON_INVALID_REFERENCE], 'null on invalid')]
    public function testOptionalReferenceToMissingServiceIsNullAfterCompilation(int $invalidBehavior)
    {
        $container = new ContainerBuilder();
        $container->register('foo', Foo::class)
            ->setPublic(true)
            ->addArgument(new LazyProxyArgument(new Reference('missing', $invalidBehavior)));

        $container->compile();

        self::assertNull($container->getDefinition('foo')->getArgument(0));
    }

    public function testTargetWithoutClassThrows()
    {
        $container = new ContainerBuilder();
        $container->register('a')->setSynthetic(true);
        $container->register('foo', Foo::class)
            ->addArgument(new LazyProxyArgument(new Reference('a')));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create a lazy proxy for service "a" because it has no class; configure the interface(s) that should be proxied instead.');

        (new ResolveLazyProxyPass())->process($container);
    }

    public function testLazyProxyArgumentIsResolvedDuringCompilation()
    {
        $container = new ContainerBuilder();
        $container->register('a', A::class);
        $container->register('foo', Foo::class)
            ->setPublic(true)
            ->addArgument(new LazyProxyArgument($reference = new TypedReference('a', A::class)));

        $container->compile();

        $argument = $container->getDefinition('foo')->getArgument(0);
        self::assertInstanceOf(LazyProxyArgument::class, $argument);

        [$service, $interfaces, $resolvedReference] = $argument->getValues();
        self::assertSame($reference, $service);
        self::assertSame([], $interfaces);
        self::assertInstanceOf(Reference::class, $resolvedReference);
        self::assertStringStartsWith('.lazy.a.', (string) $resolvedReference);
        self::assertTrue($container->getDefinition((string) $resolvedReference)->isLazy());
    }
}
