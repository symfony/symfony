<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\Exception\LogicException;
use Symfony\Component\VarExporter\LazyProxyTrait;
use Symfony\Component\VarExporter\ProxyHelper;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\FinalPublicClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\ReadOnlyClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\StringMagicGetClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestUnserializeClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\TestWakeupClass;

class LazyProxyTraitTest extends TestCase
{
    public function testGetter()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        $this->assertInstanceOf(TestClass::class, $proxy);
        $this->assertSame(0, $initCounter);
        $this->assertFalse($proxy->isLazyObjectInitialized());

        $dep1 = $proxy->getDep();
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertSame(1, $initCounter);

        $this->assertTrue($proxy->resetLazyObject());
        $this->assertSame(1, $initCounter);

        $dep2 = $proxy->getDep();
        $this->assertSame(2, $initCounter);
        $this->assertNotSame($dep1, $dep2);
    }

    public function testInitialize()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        $this->assertSame(0, $initCounter);
        $this->assertFalse($proxy->isLazyObjectInitialized());

        $proxy->initializeLazyObject();
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertSame(1, $initCounter);

        $proxy->initializeLazyObject();
        $this->assertSame(1, $initCounter);
    }

    public function testClone()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        $clone = clone $proxy;
        $this->assertSame(0, $initCounter);

        $dep1 = $proxy->getDep();
        $this->assertSame(1, $initCounter);

        $dep2 = $clone->getDep();
        $this->assertSame(2, $initCounter);

        $this->assertNotSame($dep1, $dep2);
    }

    public function testUnserialize()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestUnserializeClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestUnserializeClass((object) ['hello' => 'world']);
        });

        $this->assertInstanceOf(TestUnserializeClass::class, $proxy);
        $this->assertSame(0, $initCounter);

        $copy = unserialize(serialize($proxy));
        $this->assertSame(1, $initCounter);
        $this->assertTrue($copy->isLazyObjectInitialized());
        $this->assertTrue($proxy->isLazyObjectInitialized());

        $this->assertFalse($copy->resetLazyObject());
        $this->assertTrue($copy->getDep()->wokeUp);
        $this->assertSame('world', $copy->getDep()->hello);
    }

    public function testWakeup()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestWakeupClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestWakeupClass((object) ['hello' => 'world']);
        });

        $this->assertInstanceOf(TestWakeupClass::class, $proxy);
        $this->assertSame(0, $initCounter);

        $copy = unserialize(serialize($proxy));
        $this->assertSame(1, $initCounter);

        $this->assertFalse($copy->resetLazyObject());
        $this->assertTrue($copy->getDep()->wokeUp);
        $this->assertSame('world', $copy->getDep()->hello);
    }

    public function testDestruct()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        unset($proxy);
        $this->assertSame(0, $initCounter);

        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });
        $dep = $proxy->getDep();
        $this->assertSame(1, $initCounter);
        unset($proxy);
        $this->assertTrue($dep->destructed);
    }

    public function testDynamicProperty()
    {
        $initCounter = 0;
        $proxy = $this->createLazyProxy(TestClass::class, function () use (&$initCounter) {
            ++$initCounter;

            return new TestClass((object) ['hello' => 'world']);
        });

        $proxy->dynProp = 123;
        $this->assertSame(1, $initCounter);
        $this->assertSame(123, $proxy->dynProp);
        $this->assertTrue(isset($proxy->dynProp));
        $this->assertCount(1, (array) $proxy);
        unset($proxy->dynProp);
        $this->assertFalse(isset($proxy->dynProp));
        $this->assertCount(1, (array) $proxy);
    }

    public function testStringMagicGet()
    {
        $proxy = $this->createLazyProxy(StringMagicGetClass::class, fn () => new StringMagicGetClass());

        $this->assertSame('abc', $proxy->abc);
    }

    public function testFinalPublicClass()
    {
        $proxy = $this->createLazyProxy(FinalPublicClass::class, fn () => new FinalPublicClass());

        $this->assertSame(1, $proxy->increment());
        $this->assertSame(2, $proxy->increment());
        $this->assertSame(1, $proxy->decrement());
    }

    public function testWither()
    {
        $obj = new class() {
            public $foo = 123;

            public function withFoo($foo): static
            {
                $clone = clone $this;
                $clone->foo = $foo;

                return $clone;
            }
        };
        $proxy = $this->createLazyProxy($obj::class, fn () => $obj);

        $clone = $proxy->withFoo(234);
        $this->assertSame($clone::class, $proxy::class);
        $this->assertSame(234, $clone->foo);
        $this->assertSame(234, $obj->foo);
    }

    public function testFluent()
    {
        $obj = new class() {
            public $foo = 123;

            public function setFoo($foo): static
            {
                $this->foo = $foo;

                return $this;
            }
        };
        $proxy = $this->createLazyProxy($obj::class, fn () => $obj);

        $this->assertSame($proxy->setFoo(234), $proxy);
        $this->assertSame(234, $proxy->foo);
    }

    public function testIndirectModification()
    {
        $obj = new class() {
            public array $foo;
        };
        $proxy = $this->createLazyProxy($obj::class, fn () => $obj);

        $proxy->foo[] = 123;

        $this->assertSame([123], $proxy->foo);
    }

    /**
     * @requires PHP 8.2
     */
    public function testReadOnlyClass()
    {
        if (\PHP_VERSION_ID < 80300) {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('Cannot generate lazy proxy: class "Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\ReadOnlyClass" is readonly.');
        }

        $proxy = $this->createLazyProxy(ReadOnlyClass::class, fn () => new ReadOnlyClass(123));

        $this->assertSame(123, $proxy->foo);
    }

    public function testLazyDecoratorClass()
    {
        $obj = new class() extends TestClass {
            use LazyProxyTrait {
                createLazyProxy as private;
            }

            public function __construct()
            {
                self::createLazyProxy(fn () => new TestClass((object) ['foo' => 123]), $this);
            }
        };

        $this->assertSame(['foo' => 123], (array) $obj->getDep());
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function createLazyProxy(string $class, \Closure $initializer): object
    {
        $r = new \ReflectionClass($class);

        if (str_contains($class, "\0")) {
            $class = __CLASS__.'\\'.debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'].'_L'.$r->getStartLine();
            class_alias($r->name, $class);
        }
        $proxy = str_replace($r->name, $class, ProxyHelper::generateLazyProxy($r));
        $class = str_replace('\\', '_', $class).'_'.md5($proxy);

        if (!class_exists($class, false)) {
            eval((\PHP_VERSION_ID >= 80200 && $r->isReadOnly() ? 'readonly ' : '').'class '.$class.' '.$proxy);
        }

        return $class::createLazyProxy($initializer);
    }
}
