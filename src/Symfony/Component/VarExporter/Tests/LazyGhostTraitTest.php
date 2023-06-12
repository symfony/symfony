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
use Symfony\Component\VarExporter\ProxyHelper;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost\ChildMagicClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost\ChildStdClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost\ChildTestClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost\LazyClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost\MagicClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost\ReadOnlyClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost\TestClass;

class LazyGhostTraitTest extends TestCase
{
    public function testGetPublic()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $this->assertSame(-4, $instance->public);
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testGetPrivate()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $r = new \ReflectionProperty(TestClass::class, 'private');

        $this->assertSame(-3, $r->getValue($instance));
    }

    public function testIssetPublic()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $this->assertTrue(isset($instance->public));
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testUnsetPublic()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        unset($instance->public);
//        $this->assertFalse(isset($instance->public));
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testSetPublic()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $instance->public = 12;
        $this->assertSame(12, $instance->public);
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testSetPrivate()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $r = new \ReflectionProperty(TestClass::class, 'private');
        $r->setValue($instance, 3);

        $this->assertSame(3, $r->getValue($instance));
    }

    public function xtestClone()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $clone = clone $instance;

        $this->assertNotSame((array) $instance, (array) $clone);
        $this->assertSame(["\0".TestClass::class."\0lazyObjectState"], array_keys((array) $instance));
        $this->assertSame(["\0".TestClass::class."\0lazyObjectState"], array_keys((array) $clone));

        $clone = clone $clone;
        $this->assertTrue($clone->resetLazyObject());
    }

    public function testSerialize()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $serialized = serialize($instance);
        $this->assertStringNotContainsString('lazyObjectState', $serialized);

        $clone = unserialize($serialized);
        $expected = (array) $instance;
        unset($expected["\0".TestClass::class."\0lazyObjectState"]);
        $this->assertSame(array_keys($expected), array_keys((array) $clone));
        //$this->assertFalse($clone->resetLazyObject());
        //$this->assertTrue($clone->isLazyObjectInitialized());
    }

    /**
     * @dataProvider provideMagicClass
     */
    public function testMagicClass(MagicClass $instance)
    {
        $this->assertSame('bar', $instance->foo);
        $instance->foo = 123;
        $this->assertSame(123, $instance->foo);
        $this->assertTrue(isset($instance->foo));
        unset($instance->foo);
        $this->assertFalse(isset($instance->foo));

        $clone = clone $instance;
        $this->assertSame(0, $instance->cloneCounter);
        $this->assertSame(1, $clone->cloneCounter);

        $instance->bar = 123;
        $serialized = serialize($instance);
        $clone = unserialize($serialized);
        $this->assertSame(123, $clone->bar);
    }

    public static function provideMagicClass()
    {
        yield [new MagicClass()];

        yield [ChildMagicClass::createLazyGhost(function (ChildMagicClass $instance) {
            $instance->__construct();
        })];
    }

    public function testResetLazyGhost()
    {
        $instance = ChildMagicClass::createLazyGhost(function (ChildMagicClass $instance) {
            $instance->__construct();
        });

        $instance->foo = 234;
        $this->assertTrue($instance->resetLazyObject());
        $this->assertFalse($instance->isLazyObjectInitialized());
        $this->assertSame('bar', $instance->foo);
    }

    public function testFullInitialization()
    {
        $counter = 0;
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) use (&$counter) {
            ++$counter;
            $ghost->__construct();
        });

        $this->assertFalse($instance->isLazyObjectInitialized());
        $this->assertTrue(isset($instance->public));
        $this->assertTrue($instance->isLazyObjectInitialized());
        $this->assertSame(-4, $instance->public);
        $this->assertSame(4, $instance->publicReadonly);
        $this->assertSame(1, $counter);
    }

    public function testSetStdClassProperty()
    {
        $instance = ChildStdClass::createLazyGhost(function (ChildStdClass $ghost) {
        });

        $instance->public = 12;
        $this->assertSame(12, $instance->public);
    }

    public function xtestLazyClass()
    {
        $obj = new LazyClass(fn ($proxy) => $proxy->public = 123);

        $this->assertSame(123, $obj->public);
    }

    public function testReflectionPropertyGetValue()
    {
        $obj = TestClass::createLazyGhost(fn ($proxy) => $proxy->__construct());

        $r = new \ReflectionProperty($obj, 'private');

        $this->assertSame(-3, $r->getValue($obj));
    }

    public function testIndirectModification()
    {
        $obj = new class() {
            public array $foo;
        };
        $proxy = $this->createLazyGhost($obj::class, fn () => null);

        $proxy->foo[] = 123;

        $this->assertSame([123], $proxy->foo);
    }

    /**
     * @requires PHP 8.3
     */
    public function testReadOnlyClass()
    {
        $proxy = $this->createLazyGhost(ReadOnlyClass::class, fn ($proxy) => $proxy->__construct(123));

        $this->assertSame(123, $proxy->foo);
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function createLazyGhost(string $class, \Closure $initializer, array $skippedProperties = null): object
    {
        $r = new \ReflectionClass($class);

        if (str_contains($class, "\0")) {
            $class = __CLASS__.'\\'.debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'].'_L'.$r->getStartLine();
            class_alias($r->name, $class);
        }
        $proxy = str_replace($r->name, $class, ProxyHelper::generateLazyGhost($r));
        $class = str_replace('\\', '_', $class).'_'.md5($proxy);

        if (!class_exists($class, false)) {
            eval(($r->isReadOnly() ? 'readonly ' : '').'class '.$class.' '.$proxy);
        }

        return $class::createLazyGhost($initializer, $skippedProperties);
    }
}
