<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Read;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\RuntimeException;
use Symfony\Component\JsonStreamer\Read\LazyInstantiator;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;

class LazyInstantiatorTest extends TestCase
{
    public function testCreateLazyGhostUsingPhp()
    {
        $ghost = (new LazyInstantiator())->instantiate(ClassicDummy::class, static function (ClassicDummy $object): void {
            $object->id = 123;
        });

        $this->assertSame(123, $ghost->id);
    }

    public function testInstantiateInternalClassEagerly()
    {
        $object = (new LazyInstantiator())->instantiate(\DateTimeImmutable::class, static function (\DateTimeImmutable $object): void {
        });

        $this->assertInstanceOf(\DateTimeImmutable::class, $object);
    }

    public function testInstantiateClassWithPrivateProperties()
    {
        $class = new class {
            private int $value = 0;

            public function getValue(): int
            {
                return $this->value;
            }
        };

        $ghost = (new LazyInstantiator())->instantiate($class::class, static function (object $object): void {
            $reflection = new \ReflectionProperty($object, 'value');
            $reflection->setValue($object, 42);
        });

        $this->assertSame(42, $ghost->getValue());
    }

    public function testInstantiateClassWithReadonlyProperties()
    {
        $ghost = (new LazyInstantiator())->instantiate(ReadonlyDummy::class, static function (ReadonlyDummy $object): void {
            $reflection = new \ReflectionProperty($object, 'id');
            $reflection->setValue($object, 7);
        });

        $this->assertSame(7, $ghost->id);
    }

    public function testReusesCachedReflectionAcrossCalls()
    {
        $instantiator = new LazyInstantiator();

        $first = $instantiator->instantiate(ClassicDummy::class, static function (ClassicDummy $object): void {
            $object->id = 1;
        });
        $second = $instantiator->instantiate(ClassicDummy::class, static function (ClassicDummy $object): void {
            $object->id = 2;
        });

        $this->assertSame(1, $first->id);
        $this->assertSame(2, $second->id);
        $this->assertNotSame($first, $second);
    }

    public function testInitializerErrorPropagates()
    {
        $instantiator = new LazyInstantiator();
        $ghost = $instantiator->instantiate(ClassicDummy::class, static function (ClassicDummy $object): void {
            throw new \DomainException('initializer failed');
        });

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('initializer failed');

        $ghost->id;
    }

    public function testThrowsRuntimeExceptionWhenReflectionFails()
    {
        $this->expectException(RuntimeException::class);

        (new LazyInstantiator())->instantiate('Symfony\\NotAClass\\Missing', static function (object $object): void {
        });
    }
}

final class ReadonlyDummy
{
    public function __construct(
        public readonly int $id = 0,
    ) {
    }
}
