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
use Symfony\Component\VarExporter\Internal\GhostObjectId;
use Symfony\Component\VarExporter\Internal\GhostObjectRegistry;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhostObject\ChildMagicClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhostObject\ChildTestClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhostObject\MagicClass;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyGhostObject\TestClass;

class LazyGhostObjectTraitTest extends TestCase
{
    public function testGetPublic()
    {
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $this->assertSame(["\0".TestClass::class."\0lazyGhostObjectId"], array_keys((array) $instance));
        $this->assertSame(-4, $instance->public);
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testIssetPublic()
    {
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $this->assertSame(["\0".TestClass::class."\0lazyGhostObjectId"], array_keys((array) $instance));
        $this->assertTrue(isset($instance->public));
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testUnsetPublic()
    {
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $this->assertSame(["\0".TestClass::class."\0lazyGhostObjectId"], array_keys((array) $instance));
        unset($instance->public);
        $this->assertFalse(isset($instance->public));
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testSetPublic()
    {
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $this->assertSame(["\0".TestClass::class."\0lazyGhostObjectId"], array_keys((array) $instance));
        $instance->public = 12;
        $this->assertSame(12, $instance->public);
        $this->assertSame(4, $instance->publicReadonly);
    }

    public function testClone()
    {
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $clone = clone $instance;

        $this->assertNotSame((array) $instance, (array) $clone);
        $this->assertSame(["\0".TestClass::class."\0lazyGhostObjectId"], array_keys((array) $instance));
        $this->assertSame(["\0".TestClass::class."\0lazyGhostObjectId"], array_keys((array) $clone));

        $clone = clone $clone;
        $this->assertTrue($clone->resetLazyGhostObject());
    }

    public function testSerialize()
    {
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $ghost) {
            $ghost->__construct();
        });

        $serialized = serialize($instance);
        $this->assertStringNotContainsString('lazyGhostObjectId', $serialized);

        $clone = unserialize($serialized);
        $this->assertSame(array_keys((array) $instance), array_keys((array) $clone));
        $this->assertFalse($clone->resetLazyGhostObject());
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

    public function provideMagicClass()
    {
        yield [new MagicClass()];

        yield [ChildMagicClass::createLazyGhostObject(function (ChildMagicClass $instance) {
            $instance->__construct();
        })];
    }

    public function testDestruct()
    {
        $registryCount = \count(GhostObjectRegistry::$states);
        $destructCounter = MagicClass::$destructCounter;

        $instance = ChildMagicClass::createLazyGhostObject(function (ChildMagicClass $instance) {
            $instance->__construct();
        });

        unset($instance);
        $this->assertSame($destructCounter, MagicClass::$destructCounter);

        $instance = ChildMagicClass::createLazyGhostObject(function (ChildMagicClass $instance) {
            $instance->__construct();
        });
        $instance->initializeLazyGhostObject();
        unset($instance);

        $this->assertSame(1 + $destructCounter, MagicClass::$destructCounter);

        $this->assertCount($registryCount, GhostObjectRegistry::$states);
    }

    public function testResetLazyGhostObject()
    {
        $instance = ChildMagicClass::createLazyGhostObject(function (ChildMagicClass $instance) {
            $instance->__construct();
        });

        $instance->foo = 234;
        $this->assertTrue($instance->resetLazyGhostObject());
        $this->assertSame('bar', $instance->foo);
    }

    public function testFullInitialization()
    {
        $counter = 0;
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $ghost) use (&$counter) {
            ++$counter;
            $ghost->__construct();
        });

        $this->assertTrue(isset($instance->public));
        $this->assertSame(-4, $instance->public);
        $this->assertSame(4, $instance->publicReadonly);
        $this->assertSame(1, $counter);
    }

    public function testPartialInitialization()
    {
        $counter = 0;
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $instance, string $propertyName, ?string $propertyScope) use (&$counter) {
            ++$counter;

            return match ($propertyName) {
                'public' => 123,
                'publicReadonly' => 234,
                'protected' => 345,
                'protectedReadonly' => 456,
                'private' => match ($propertyScope) {
                    TestClass::class => 567,
                    ChildTestClass::class => 678,
                },
            };
        });

        $this->assertSame(["\0".TestClass::class."\0lazyGhostObjectId"], array_keys((array) $instance));
        $this->assertSame(123, $instance->public);
        $this->assertSame(['public', "\0".TestClass::class."\0lazyGhostObjectId"], array_keys((array) $instance));
        $this->assertSame(1, $counter);

        $instance->initializeLazyGhostObject();
        $this->assertSame(123, $instance->public);
        $this->assertSame(6, $counter);

        $properties = (array) $instance;
        $this->assertSame(array_keys((array) new ChildTestClass()), array_keys($properties));
        $properties = array_values($properties);
        $this->assertInstanceOf(GhostObjectId::class, array_splice($properties, 4, 1)[0]);
        $this->assertSame([123, 345, 456, 567, 234, 678], array_values($properties));
    }

    public function testPartialInitializationWithReset()
    {
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $instance, string $propertyName, ?string $propertyScope) {
            return 234;
        });

        $instance->public = 123;

        $this->assertSame(234, $instance->publicReadonly);
        $this->assertSame(123, $instance->public);

        $this->assertTrue($instance->resetLazyGhostObject());
        $this->assertSame(234, $instance->publicReadonly);
        $this->assertSame(123, $instance->public);

        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $instance, string $propertyName, ?string $propertyScope) {
            return 234;
        });

        $instance->resetLazyGhostObject();

        $instance->public = 123;
        $this->assertSame(123, $instance->public);

        $this->assertTrue($instance->resetLazyGhostObject());
        $this->assertSame(234, $instance->public);
    }

    public function testPartialInitializationWithNastyPassByRef()
    {
        $instance = ChildTestClass::createLazyGhostObject(function (ChildTestClass $instance, string &$propertyName, ?string &$propertyScope) {
            return $propertyName = $propertyScope = 123;
        });

        $this->assertSame(123, $instance->public);
    }
}
