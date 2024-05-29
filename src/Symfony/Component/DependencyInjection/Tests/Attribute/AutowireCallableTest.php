<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class AutowireCallableTest extends TestCase
{
    public function testNoArguments()
    {
        $this->expectException(LogicException::class);

        new AutowireCallable();
    }

    public function testCallableAndService()
    {
        $this->expectException(LogicException::class);

        new AutowireCallable(callable: 'my_callable', service: 'my_service', method: 'my_method');
    }

    public function testMethodOnly()
    {
        $this->expectException(LogicException::class);

        new AutowireCallable(method: 'my_method');
    }

    public function testCallableAndMethod()
    {
        $this->expectException(LogicException::class);

        new AutowireCallable(callable: 'my_callable', method: 'my_method');
    }

    public function testStringCallable()
    {
        $attribute = new AutowireCallable(callable: 'my_callable');

        self::assertSame('my_callable', $attribute->value);
        self::assertFalse($attribute->lazy);
    }

    public function testArrayCallable()
    {
        $attribute = new AutowireCallable(callable: ['My\StaticClass', 'my_callable']);

        self::assertSame(['My\StaticClass', 'my_callable'], $attribute->value);
        self::assertFalse($attribute->lazy);
    }

    public function testArrayCallableWithReferenceAndMethod()
    {
        $attribute = new AutowireCallable(callable: [new Reference('my_service'), 'my_callable']);

        self::assertEquals([new Reference('my_service'), 'my_callable'], $attribute->value);
        self::assertFalse($attribute->lazy);
    }

    public function testArrayCallableWithReferenceOnly()
    {
        $attribute = new AutowireCallable(callable: [new Reference('my_service')]);

        self::assertEquals([new Reference('my_service')], $attribute->value);
        self::assertFalse($attribute->lazy);
    }

    public function testArrayCallableWithServiceAndMethod()
    {
        $attribute = new AutowireCallable(service: 'my_service', method: 'my_callable');

        self::assertEquals([new Reference('my_service'), 'my_callable'], $attribute->value);
        self::assertFalse($attribute->lazy);
    }

    public function testArrayCallableWithServiceOnly()
    {
        $attribute = new AutowireCallable(service: 'my_service');

        self::assertEquals([new Reference('my_service'), '__invoke'], $attribute->value);
        self::assertFalse($attribute->lazy);
    }
}
