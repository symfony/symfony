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
use Symfony\Component\VarExporter\Internal\LazyObjectState;
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

        $this->assertSame(["\0".TestClass::class."\0lazyObjectState"], array_keys((array) $instance));
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

        $this->assertSame(["\0".TestClass::class."\0lazyObjectState"], array_keys((array) $instance));
        $this->assertTrue(isset($instance->public));
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testUnsetPublic()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $this->assertSame(["\0".TestClass::class."\0lazyObjectState"], array_keys((array) $instance));
        unset($instance->public);
        $this->assertFalse(isset($instance->public));
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testSetPublic()
    {
        $instance = ChildTestClass::createLazyGhost(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $this->assertSame(["\0".TestClass::class."\0lazyObjectState"], array_keys((array) $instance));
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

    public function testClone()
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
        $this->assertArrayHasKey("\0".TestClass::class."\0lazyObjectState", $expected);
        unset($expected["\0".TestClass::class."\0lazyObjectState"]);
        $this->assertSame(array_keys($expected), array_keys((array) $clone));
        $this->assertFalse($clone->resetLazyObject());
        $this->assertTrue($clone->isLazyObjectInitialized());
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

    public function testPartialInitialization()
    {
        $counter = 0;
        $instance = ChildTestClass::createLazyGhost([
            'public' => static function (ChildTestClass $instance, string $property, ?string $scope, mixed $default) use (&$counter) {
                ++$counter;

                return 4 === $default ? 123 : -1;
            },
            'publicReadonly' => static function (ChildTestClass $instance, string $property, ?string $scope, mixed $default) use (&$counter) {
                ++$counter;

                return 234;
            },
            "\0*\0protected" => static function (ChildTestClass $instance, string $property, ?string $scope, mixed $default) use (&$counter) {
                ++$counter;

                return 5 === $default ? 345 : -1;
            },
            "\0*\0protectedReadonly" => static function (ChildTestClass $instance, string $property, ?string $scope, mixed $default) use (&$counter) {
                ++$counter;

                return 456;
            },
            "\0".TestClass::class."\0private" => static function (ChildTestClass $instance, string $property, ?string $scope, mixed $default) use (&$counter) {
                ++$counter;

                return 3 === $default ? 567 : -1;
            },
            "\0".ChildTestClass::class."\0private" => static function (ChildTestClass $instance, string $property, ?string $scope, mixed $default) use (&$counter) {
                ++$counter;

                return 6 === $default ? 678 : -1;
            },
            'dummyProperty' => fn () => 123,
        ]);

        $this->assertSame(["\0".TestClass::class."\0lazyObjectState"], array_keys((array) $instance));
        $this->assertFalse($instance->isLazyObjectInitialized());
        $this->assertSame(123, $instance->public);
        $this->assertFalse($instance->isLazyObjectInitialized());
        $this->assertTrue($instance->isLazyObjectInitialized(true));
        $this->assertSame(['public', "\0".TestClass::class."\0lazyObjectState"], array_keys((array) $instance));
        $this->assertSame(1, $counter);

        $instance->initializeLazyObject();
        $this->assertTrue($instance->isLazyObjectInitialized());
        $this->assertSame(123, $instance->public);
        $this->assertSame(6, $counter);

        $properties = (array) $instance;
        $this->assertInstanceOf(LazyObjectState::class, $properties["\0".TestClass::class."\0lazyObjectState"]);
        unset($properties["\0".TestClass::class."\0lazyObjectState"]);
        $this->assertSame(array_keys((array) new ChildTestClass()), array_keys($properties));
        $this->assertSame([123, 345, 456, 567, 234, 678], array_values($properties));
    }

    public function testPartialInitializationWithReset()
    {
        $initializer = static fn (ChildTestClass $instance, string $property, ?string $scope, mixed $default) => 234;
        $instance = ChildTestClass::createLazyGhost([
            'public' => $initializer,
            'publicReadonly' => $initializer,
            "\0*\0protected" => $initializer,
        ]);

        $r = new \ReflectionProperty($instance, 'public');
        $r->setValue($instance, 123);

        $this->assertFalse($instance->isLazyObjectInitialized());
        $this->assertSame(234, $instance->publicReadonly);
        $this->assertFalse($instance->isLazyObjectInitialized());
        $this->assertSame(123, $instance->public);

        $this->assertTrue($instance->resetLazyObject());
        $this->assertSame(234, $instance->publicReadonly);
        $this->assertSame(234, $instance->public);

        $instance = ChildTestClass::createLazyGhost(['public' => $initializer]);

        $instance->resetLazyObject();

        $instance->public = 123;
        $this->assertSame(123, $instance->public);

        $this->assertTrue($instance->resetLazyObject());
        $this->assertSame(234, $instance->public);
    }

    public function testPartialInitializationWithNastyPassByRef()
    {
        $instance = ChildTestClass::createLazyGhost(['public' => fn (ChildTestClass $instance, string &$property, ?string &$scope, mixed $default) => $property = $scope = 123]);

        $this->assertSame(123, $instance->public);
    }

    public function testSetStdClassProperty()
    {
        $instance = ChildStdClass::createLazyGhost(function (ChildStdClass $ghost) {
        });

        $instance->public = 12;
        $this->assertSame(12, $instance->public);
    }

    public function testLazyClass()
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

    public function testFullPartialInitialization()
    {
        $counter = 0;
        $initializer = static fn (ChildTestClass $instance, string $property, ?string $scope, mixed $default) => 234;
        $instance = ChildTestClass::createLazyGhost([
            'public' => $initializer,
            'publicReadonly' => $initializer,
            "\0*\0protected" => $initializer,
            "\0" => function ($obj, $defaults) use (&$instance, &$counter) {
                $counter += 1000;
                $this->assertSame($instance, $obj);

                return [
                    'public' => 345,
                    'publicReadonly' => 456,
                    "\0*\0protected" => 567,
                ] + $defaults;
            },
        ]);

        $this->assertSame($instance, $instance->initializeLazyObject());
        $this->assertSame(345, $instance->public);
        $this->assertSame(456, $instance->publicReadonly);
        $this->assertSame(6, ((array) $instance)["\0".ChildTestClass::class."\0private"]);
        $this->assertSame(3, ((array) $instance)["\0".TestClass::class."\0private"]);
        $this->assertSame(1000, $counter);
    }

    public function testPartialInitializationFallback()
    {
        $counter = 0;
        $instance = ChildTestClass::createLazyGhost([
            "\0" => function ($obj) use (&$instance, &$counter) {
                $counter += 1000;
                $this->assertSame($instance, $obj);

                return [
                    'public' => 345,
                    'publicReadonly' => 456,
                    "\0*\0protected" => 567,
                ];
            },
        ], []);

        $this->assertSame(345, $instance->public);
        $this->assertSame(456, $instance->publicReadonly);
        $this->assertSame(567, ((array) $instance)["\0*\0protected"]);
        $this->assertSame(1000, $counter);
    }

    public function testFullInitializationAfterPartialInitialization()
    {
        $counter = 0;
        $initializer = static function (ChildTestClass $instance, string $property, ?string $scope, mixed $default) use (&$counter) {
            ++$counter;

            return 234;
        };
        $instance = ChildTestClass::createLazyGhost([
            'public' => $initializer,
            'publicReadonly' => $initializer,
            "\0*\0protected" => $initializer,
            "\0" => function ($obj, $defaults) use (&$instance, &$counter) {
                $counter += 1000;
                $this->assertSame($instance, $obj);

                return [
                    'public' => 345,
                    'publicReadonly' => 456,
                    "\0*\0protected" => 567,
                ] + $defaults;
            },
        ]);

        $this->assertSame(234, $instance->public);
        $this->assertSame($instance, $instance->initializeLazyObject());
        $this->assertSame(234, $instance->public);
        $this->assertSame(456, $instance->publicReadonly);
        $this->assertSame(6, ((array) $instance)["\0".ChildTestClass::class."\0private"]);
        $this->assertSame(3, ((array) $instance)["\0".TestClass::class."\0private"]);
        $this->assertSame(1001, $counter);
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
    private function createLazyGhost(string $class, \Closure|array $initializer, array $skippedProperties = null): object
    {
        $r = new \ReflectionClass($class);

        if (str_contains($class, "\0")) {
            $class = __CLASS__.'\\'.debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'].'_L'.$r->getStartLine();
            class_alias($r->name, $class);
        }
        $proxy = str_replace($r->name, $class, ProxyHelper::generateLazyGhost($r));
        $class = str_replace('\\', '_', $class).'_'.md5($proxy);

        if (!class_exists($class, false)) {
            eval((\PHP_VERSION_ID >= 80200 && $r->isReadOnly() ? 'readonly ' : '').'class '.$class.' '.$proxy);
        }

        return $class::createLazyGhost($initializer, $skippedProperties);
    }
}
