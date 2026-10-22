<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Argument;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\LazyClosure;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Reference;

class LazyClosureTest extends TestCase
{
    public function testMagicGetThrows()
    {
        $closure = new LazyClosure(static fn () => null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot read property "foo" from a lazy closure.');

        $closure->foo;
    }

    public function testThrowsWhenNotUsingInterface()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create adapter for service "foo" because "Symfony\Component\DependencyInjection\Tests\Argument\LazyClosureTest" is not an interface.');

        LazyClosure::getCode('foo', [new \stdClass(), 'bar'], self::class, new ContainerBuilder(), 'foo');
    }

    public function testThrowsOnNonFunctionalInterface()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create adapter for service "foo" because interface "Symfony\Component\DependencyInjection\Tests\Argument\NonFunctionalInterface" doesn\'t have exactly one method.');

        LazyClosure::getCode('foo', [new \stdClass(), 'bar'], NonFunctionalInterface::class, new ContainerBuilder(), 'foo');
    }

    public function testThrowsOnUnknownMethodInInterface()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create lazy closure for service "bar" because its corresponding callable is invalid.');

        LazyClosure::getCode('bar', [new Definition(FunctionalInterface::class), 'bar'], \Closure::class, new ContainerBuilder(), 'bar');
    }

    public function testLazyClosureWithStaticMethod()
    {
        $container = new ContainerBuilder();
        $container->register('target', InheritedStaticCallable::class)->setPublic(true);
        foreach (['closure' => \Closure::class, 'adapter' => FunctionalInterface::class] as $id => $class) {
            $container->register($id, $class)
                ->setPublic(true)
                ->setFactory(['Closure', 'fromCallable'])
                ->setArguments([[new Reference('target'), 'create']])
                ->setLazy(true);
        }
        $container->compile();

        eval('?>'.(new PhpDumper($container))->dump(['class' => 'Symfony_DI_LazyClosure_StaticMethod']));

        foreach ([$container, new \Symfony_DI_LazyClosure_StaticMethod()] as $container) {
            $closure = $container->get('closure');
            $adapter = $container->get('adapter');

            $this->assertFalse($container->initialized('target'));
            $this->assertInstanceOf(InheritedStaticCallable::class, $closure());
            $this->assertTrue($container->initialized('target'));
            $this->assertInstanceOf(InheritedStaticCallable::class, $adapter->foo());
        }
    }

    public function testLazyClosureWithNeverReturnType()
    {
        $container = new ContainerBuilder();
        $container->register('target', NeverReturningCallable::class)->setPublic(true);
        foreach (['closure' => \Closure::class, 'adapter' => NeverReturningInterface::class] as $id => $class) {
            $container->register($id, $class)
                ->setPublic(true)
                ->setFactory(['Closure', 'fromCallable'])
                ->setArguments([[new Reference('target'), '__invoke']])
                ->setLazy(true);
        }
        $container->compile();

        eval('?>'.(new PhpDumper($container))->dump(['class' => 'Symfony_DI_LazyClosure_NeverReturnType']));

        foreach ([$container, new \Symfony_DI_LazyClosure_NeverReturnType()] as $container) {
            $callbacks = [$container->get('closure'), $container->get('adapter')];

            $this->assertFalse($container->initialized('target'));
            foreach ($callbacks as $callback) {
                try {
                    $callback();
                } catch (\DomainException $e) {
                    $this->assertSame('Call failed.', $e->getMessage());
                    $this->assertTrue($container->initialized('target'));
                    continue;
                }

                $this->fail('The callable did not throw.');
            }
        }
    }
}

interface FunctionalInterface
{
    public function foo();
}

interface NonFunctionalInterface
{
    public function foo();

    public function bar();
}

class StaticCallable
{
    public static function create(): static
    {
        return new static();
    }
}

class InheritedStaticCallable extends StaticCallable
{
}

interface NeverReturningInterface
{
    public function __invoke(): never;
}

class NeverReturningCallable
{
    public function __invoke(): never
    {
        throw new \DomainException('Call failed.');
    }
}
