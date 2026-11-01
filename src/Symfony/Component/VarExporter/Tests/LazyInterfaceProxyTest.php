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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Component\VarExporter\ProxyHelper;

class LazyInterfaceProxyTest extends TestCase
{
    public function testNativeInitialization()
    {
        $calls = 0;
        $inner = new NativeProxyService(42);
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls, $inner) {
            ++$calls;

            return $inner;
        });
        $reflector = new \ReflectionClass($proxy);

        $this->assertInstanceOf(LazyObjectInterface::class, $proxy);
        $this->assertTrue($reflector->isUninitializedLazyObject($proxy));
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertFalse($proxy->isLazyObjectInitialized(true));
        $this->assertTrue($proxy->resetLazyObject());
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertTrue($reflector->isUninitializedLazyObject($proxy));
        $this->assertSame(0, $calls);

        $this->assertSame(42, $proxy->getValue());
        $this->assertFalse($reflector->isUninitializedLazyObject($proxy));
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertSame($inner, $proxy->initializeLazyObject());
        $this->assertSame(1, $calls);
    }

    public function testResetAfterInitialization()
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls) {
            return new NativeProxyService(++$calls);
        });

        $first = $proxy->initializeLazyObject();
        $this->assertSame(1, $first->getValue());
        $this->assertTrue($proxy->resetLazyObject());
        $this->assertTrue($proxy->resetLazyObject());
        $this->assertSame(1, $calls);
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertTrue((new \ReflectionClass($proxy))->isUninitializedLazyObject($proxy));

        $second = $proxy->initializeLazyObject();
        $this->assertNotSame($first, $second);
        $this->assertSame(2, $second->getValue());
        $this->assertSame(2, $proxy->getValue());
        $this->assertSame(2, $calls);
    }

    public function testRecreateExistingProxy()
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls) {
            ++$calls;

            return new NativeProxyService(1);
        });
        $initializer = static function () use (&$calls) {
            return new NativeProxyService(40 + ++$calls);
        };

        $this->assertSame($proxy, $proxy::createLazyProxy($initializer, $proxy));
        $this->assertSame(0, $calls);
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertTrue((new \ReflectionClass($proxy))->isUninitializedLazyObject($proxy));
        $this->assertSame(41, $proxy->getValue());

        $first = $proxy->initializeLazyObject();
        $this->assertSame($proxy, $proxy::createLazyProxy($initializer, $proxy));
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertSame(1, $calls);
        $this->assertSame(42, $proxy->getValue());
        $this->assertNotSame($first, $proxy->initializeLazyObject());
        $this->assertSame(2, $calls);
    }

    public function testRetryFailedInitializer()
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls) {
            if (1 === ++$calls) {
                throw new \RuntimeException('The first initialization failed.');
            }

            return new NativeProxyService(42);
        });

        try {
            $proxy->getValue();
            $this->fail('The first initialization should fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('The first initialization failed.', $e->getMessage());
        }

        $this->assertSame(1, $calls);
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertTrue((new \ReflectionClass($proxy))->isUninitializedLazyObject($proxy));
        $this->assertSame(42, $proxy->getValue());
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertSame(2, $calls);
    }

    public function testCloneBeforeInitialization()
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls) {
            ++$calls;

            return new NativeProxyService(42);
        });

        $clone = clone $proxy;

        $this->assertSame(1, $calls);
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertTrue($clone->isLazyObjectInitialized());
        $this->assertNotSame($proxy->initializeLazyObject(), $clone->initializeLazyObject());
        $reference = &$clone->reference();
        $reference = 84;
        $this->assertSame(84, $clone->getValue());
        $this->assertSame(42, $proxy->getValue());

        $this->assertTrue($clone->resetLazyObject());
        $this->assertFalse($clone->isLazyObjectInitialized());
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertSame(42, $clone->getValue());
        $this->assertSame(2, $calls);
    }

    public function testSerializeBeforeInitialization()
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls) {
            ++$calls;

            return new NativeProxyService(42);
        });

        $copy = unserialize(serialize($proxy));

        $this->assertSame(1, $calls);
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertTrue($copy->isLazyObjectInitialized());
        $this->assertSame(42, $copy->getValue());
        $this->assertFalse($copy->resetLazyObject());
        $this->assertCount(1, $proxy->__serialize());
        $this->assertSame($proxy->initializeLazyObject(), $proxy->__serialize()[0]);
        unset($copy->value);
        $this->assertSame(42, $copy->getValue());

        $copy::createLazyProxy(static fn () => new NativeProxyService(84), $copy);
        $this->assertFalse($copy->isLazyObjectInitialized());
        $this->assertTrue((new \ReflectionClass($copy))->isUninitializedLazyObject($copy));
        $this->assertSame(84, $copy->getValue());
        $this->assertSame(1, $calls);
    }

    public function testFluentMethodsAndReferences()
    {
        $inner = new NativeProxyService(42);
        $proxy = $this->createProxy(NativeProxyInterface::class, static fn () => $inner);

        $this->assertSame($proxy, $proxy->fluent());
        $wither = $proxy->withValue(84);
        $this->assertSame($proxy::class, $wither::class);
        $this->assertNotSame($proxy, $wither);
        $this->assertSame(84, $wither->getValue());
        $this->assertSame(42, $proxy->getValue());

        $amount = 1;
        $proxy->add($amount, 2, 3);
        $this->assertSame(2, $amount);
        $this->assertSame(48, $proxy->getValue());
        $reference = &$proxy->reference();
        $reference = 21;
        $this->assertSame(21, $inner->getValue());
        $this->assertSame(84, $wither->getValue());
        $this->assertTrue($wither->resetLazyObject());
        $this->assertFalse($wither->isLazyObjectInitialized());
        $this->assertTrue($proxy->isLazyObjectInitialized());
    }

    public function testUnusedProxyDoesNotInitializeOnDestruction()
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls) {
            ++$calls;

            return new NativeProxyService(42);
        });

        unset($proxy);

        $this->assertSame(0, $calls);
    }

    public function testCapturedContainerCanBeCollected()
    {
        $container = new \stdClass();
        $proxy = $this->createProxy(NativeProxyInterface::class, static fn () => new NativeProxyService(isset($container->proxy) ? 42 : 0));
        $container->proxy = $proxy;
        $containerReference = \WeakReference::create($container);
        $proxyReference = \WeakReference::create($proxy);
        unset($container, $proxy);

        gc_collect_cycles();

        $this->assertNull($containerReference->get());
        $this->assertNull($proxyReference->get());
    }

    public function testResetDoesNotCallSubclassDestructor()
    {
        $base = $this->createProxy(NativeProxyInterface::class, static fn () => new NativeProxyService(42));
        $class = 'NativeInterfaceDestructorProxy'.md5($base::class);

        if (!class_exists($class, false)) {
            eval('class '.$class.' extends \\'.$base::class.' {
                public static int $destructions = 0;

                public function __destruct()
                {
                    ++self::$destructions;
                }
            }');
        }

        $class::$destructions = 0;
        $calls = 0;
        $initializer = static function () use (&$calls) {
            return new NativeProxyService(++$calls);
        };
        $proxy = $class::createLazyProxy($initializer);

        $this->assertTrue($proxy->resetLazyObject());
        $this->assertSame(0, $class::$destructions);
        $this->assertSame($proxy, $class::createLazyProxy($initializer, $proxy));
        $this->assertSame(0, $class::$destructions);
        $this->assertSame(0, $calls);

        $this->assertSame(1, $proxy->getValue());
        $this->assertTrue($proxy->resetLazyObject());
        $this->assertSame(0, $class::$destructions);
        $this->assertSame(2, $proxy->getValue());
        $this->assertSame($proxy, $class::createLazyProxy($initializer, $proxy));
        $this->assertSame(0, $class::$destructions);
        $this->assertSame(2, $calls);
        $this->assertSame(3, $proxy->getValue());

        unset($proxy);

        $this->assertSame(1, $class::$destructions);
    }

    public function testMagicProperties()
    {
        $calls = 0;
        $inner = new NativeProxyService(42);
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls, $inner) {
            ++$calls;

            return $inner;
        });

        $this->assertTrue(isset($proxy->value));
        $this->assertSame(1, $calls);
        $this->assertSame(42, $proxy->value);
        $proxy->value = 84;
        $this->assertSame(84, $inner->getValue());
        ++$proxy->value;
        $this->assertSame(85, $inner->getValue());
        unset($proxy->value);
        $this->assertFalse(isset($proxy->value));
        $proxy->value = 21;
        $this->assertSame(21, $inner->getValue());
        $this->assertSame(1, $calls);
    }

    public function testUnsetBeforeInitialization()
    {
        $calls = 0;
        $inner = new NativeProxyService(42);
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls, $inner) {
            ++$calls;

            return $inner;
        });

        unset($proxy->value);

        $this->assertSame(1, $calls);
        $this->assertTrue($proxy->isLazyObjectInitialized());
        $this->assertFalse(isset($inner->value));
    }

    public function testTypedMagicGetter()
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyStringGetter::class, static function () use (&$calls) {
            ++$calls;

            return new NativeProxyMagicService();
        });

        $this->assertSame('NAME', $proxy->name);
        $this->assertSame(1, $calls);
        $this->assertTrue($proxy->isLazyObjectInitialized());
    }

    #[DataProvider('provideFirstPropertyAccess')]
    public function testInterfaceProperties(bool $writeFirst)
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyPropertiesInterface::class, static function () use (&$calls) {
            ++$calls;

            return new NativeProxyPropertiesService();
        });

        $this->assertSame(0, $calls);

        if ($writeFirst) {
            $proxy->values = ['written'];
            $this->assertSame(['written'], $proxy->initializeLazyObject()->values);
        } else {
            $this->assertSame(['initial'], $proxy->values);
        }

        $this->assertSame(1, $calls);
        $proxy->values[] = 'next';
        $this->assertSame([$writeFirst ? 'written' : 'initial', 'next'], $proxy->initializeLazyObject()->values);
        $this->assertTrue($proxy->resetLazyObject());
        $this->assertSame(1, $calls);
        $this->assertSame(['initial'], $proxy->values);
        $this->assertSame(2, $calls);
    }

    public static function provideFirstPropertyAccess(): iterable
    {
        yield [false];
        yield [true];
    }

    #[DataProvider('provideWrapperModifiers')]
    public function testWrapperModifiers(string $modifiers, bool $native)
    {
        $calls = 0;
        $proxy = $this->createProxy(NativeProxyInterface::class, static function () use (&$calls) {
            return new NativeProxyService(++$calls);
        }, $modifiers);

        $this->assertFalse($proxy->isLazyObjectInitialized());

        if ($native) {
            $this->assertTrue((new \ReflectionClass($proxy))->isUninitializedLazyObject($proxy));
        }

        $this->assertSame(1, $proxy->getValue());
        $this->assertTrue($proxy->resetLazyObject());
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertSame(2, $proxy->getValue());

        $proxy::createLazyProxy(static fn () => new NativeProxyService(42), $proxy);
        $this->assertFalse($proxy->isLazyObjectInitialized());
        $this->assertSame(42, $proxy->getValue());
        $this->assertSame(2, $calls);
        $this->assertSame($proxy, $proxy->fluent());
        $this->assertSame(84, $proxy->withValue(84)->getValue());
        $this->assertSame(42, $proxy->getValue());
    }

    public static function provideWrapperModifiers(): iterable
    {
        yield ['readonly ', true];
        yield ['final ', false];
        yield ['final readonly ', false];
    }

    private function createProxy(string $interface, \Closure $initializer, string $modifiers = ''): object
    {
        $code = ProxyHelper::generateLazyProxy(null, [new \ReflectionClass($interface)]);
        $class = 'NativeInterfaceProxy'.md5($code.$modifiers);

        if (!class_exists($class, false)) {
            eval($modifiers.'class '.$class.$code);
        }

        return $class::createLazyProxy($initializer);
    }
}

interface NativeProxyInterface
{
    public function getValue(): int;

    public function add(int &$amount, int ...$more): void;

    public function fluent(): static;

    public function withValue(int $value): static;

    public function &reference(): int;
}

final class NativeProxyService implements NativeProxyInterface
{
    public function __construct(public int $value)
    {
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function add(int &$amount, int ...$more): void
    {
        $this->value += $amount + array_sum($more);
        ++$amount;
    }

    public function fluent(): static
    {
        return $this;
    }

    public function withValue(int $value): static
    {
        $clone = clone $this;
        $clone->value = $value;

        return $clone;
    }

    public function &reference(): int
    {
        return $this->value;
    }
}

interface NativeProxyStringGetter
{
    public function __get(string $name): string;
}

final class NativeProxyMagicService implements NativeProxyStringGetter
{
    private array $values = [];

    public function &__get(string $name): string
    {
        $this->values[$name] ??= strtoupper($name);

        return $this->values[$name];
    }
}

interface NativeProxyPropertiesInterface
{
    public array $values { get; set; }
}

final class NativeProxyPropertiesService implements NativeProxyPropertiesInterface
{
    public array $values = ['initial'];
}
