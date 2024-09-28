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

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\LazyClosure;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class LazyClosureTest extends TestCase
{
    public function testMagicGetThrows()
    {
        $closure = new LazyClosure(fn () => null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot read property "foo" from a lazy closure.');

        $closure->foo;
    }

    public function testThrowsWhenNotUsingInterface()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create adapter for service "foo" because "Symfony\Component\DependencyInjection\Tests\Argument\LazyClosureTest" is not an interface.');

        LazyClosure::getCode('foo', [new \stdClass(), 'bar'], new Definition(LazyClosureTest::class), new ContainerBuilder(), 'foo');
    }

    public function testThrowsOnNonFunctionalInterface()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create adapter for service "foo" because interface "Symfony\Component\DependencyInjection\Tests\Argument\NonFunctionalInterface" doesn\'t have exactly one method.');

        LazyClosure::getCode('foo', [new \stdClass(), 'bar'], new Definition(NonFunctionalInterface::class), new ContainerBuilder(), 'foo');
    }

    public function testThrowsOnUnknownMethodInInterface()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot create lazy closure for service "bar" because its corresponding callable is invalid.');

        LazyClosure::getCode('bar', [new Definition(FunctionalInterface::class), 'bar'], new Definition(\Closure::class), new ContainerBuilder(), 'bar');
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
