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

use Symfony\Component\VarExporter\Exception\LogicException;
use Symfony\Component\VarExporter\ProxyHelper;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\Php82NullStandaloneReturnType;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\StringMagicGetClass;

/**
 * @requires PHP < 8.4
 *
 * @group legacy
 */
class LegacyProxyHelperTest extends ProxyHelperTest
{
    public function testGenerateLazyProxy()
    {
        $expected = <<<'EOPHP'
         extends \Symfony\Component\VarExporter\Tests\TestForProxyHelper implements \Symfony\Component\VarExporter\LazyObjectInterface
        {
            use \Symfony\Component\VarExporter\LazyProxyTrait;

            private const LAZY_OBJECT_PROPERTY_SCOPES = [];

            public function foo1(): ?\Symfony\Component\VarExporter\Tests\Bar
            {
                if (isset($this->lazyObjectState)) {
                    return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->foo1(...\func_get_args());
                }

                return parent::foo1(...\func_get_args());
            }

            public function foo4(\Symfony\Component\VarExporter\Tests\Bar|string $b, &$d): void
            {
                if (isset($this->lazyObjectState)) {
                    ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->foo4($b, $d, ...\array_slice(\func_get_args(), 2));
                } else {
                    parent::foo4($b, $d, ...\array_slice(\func_get_args(), 2));
                }
            }

            protected function foo7()
            {
                if (isset($this->lazyObjectState)) {
                    return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->foo7(...\func_get_args());
                }

                return throw new \BadMethodCallException('Cannot forward abstract method "Symfony\Component\VarExporter\Tests\TestForProxyHelper::foo7()".');
            }
        }

        // Help opcache.preload discover always-needed symbols
        class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
        class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);
        class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectState::class);

        EOPHP;

        $this->assertSame($expected, ProxyHelper::generateLazyProxy(new \ReflectionClass(TestForProxyHelper::class)));
    }

    public function testGenerateLazyProxyForInterfaces()
    {
        $expected = <<<'EOPHP'
         implements \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface1, \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2, \Symfony\Component\VarExporter\LazyObjectInterface
        {
            use \Symfony\Component\VarExporter\LazyProxyTrait;

            private const LAZY_OBJECT_PROPERTY_SCOPES = [];

            public function initializeLazyObject(): \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface1&\Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2
            {
                if ($state = $this->lazyObjectState ?? null) {
                    return $state->realInstance ??= ($state->initializer)();
                }

                return $this;
            }

            public function foo1(): ?\Symfony\Component\VarExporter\Tests\Bar
            {
                if (isset($this->lazyObjectState)) {
                    return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->foo1(...\func_get_args());
                }

                return throw new \BadMethodCallException('Cannot forward abstract method "Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface1::foo1()".');
            }

            public function foo2(?\Symfony\Component\VarExporter\Tests\Bar $b, ...$d): \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2
            {
                if (isset($this->lazyObjectState)) {
                    return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->foo2(...\func_get_args());
                }

                return throw new \BadMethodCallException('Cannot forward abstract method "Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2::foo2()".');
            }

            public static function foo3(): string
            {
                throw new \BadMethodCallException('Cannot forward abstract method "Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2::foo3()".');
            }
        }

        // Help opcache.preload discover always-needed symbols
        class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
        class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);
        class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectState::class);

        EOPHP;

        $this->assertSame($expected, ProxyHelper::generateLazyProxy(null, [new \ReflectionClass(TestForProxyHelperInterface1::class), new \ReflectionClass(TestForProxyHelperInterface2::class)]));
    }

    public static function classWithUnserializeMagicMethodProvider(): iterable
    {
        yield 'not type hinted __unserialize method' => [new class {
            public function __unserialize($array): void
            {
            }
        }, <<<'EOPHP'
        implements \Symfony\Component\VarExporter\LazyObjectInterface
        {
            use \Symfony\Component\VarExporter\LazyProxyTrait {
                __unserialize as private __doUnserialize;
            }

            private const LAZY_OBJECT_PROPERTY_SCOPES = [];

            public function __unserialize($data): void
            {
                $this->__doUnserialize($data);
            }
        }
        EOPHP];

        yield 'type hinted __unserialize method' => [new class {
            public function __unserialize(array $array): void
            {
            }
        }, <<<'EOPHP'
        implements \Symfony\Component\VarExporter\LazyObjectInterface
        {
            use \Symfony\Component\VarExporter\LazyProxyTrait;

            private const LAZY_OBJECT_PROPERTY_SCOPES = [];
        }
        EOPHP];
    }

    public function testAttributes()
    {
        $expected = <<<'EOPHP'

            public function foo(#[\SensitiveParameter] $a): int
            {
                if (isset($this->lazyObjectState)) {
                    return ($this->lazyObjectState->realInstance ??= ($this->lazyObjectState->initializer)())->foo(...\func_get_args());
                }

                return parent::foo(...\func_get_args());
            }
        }

        EOPHP;

        $class = new \ReflectionClass(new class {
            #[SomeAttribute]
            public function foo(#[\SensitiveParameter, AnotherAttribute] $a): int
            {
            }
        });

        $this->assertStringContainsString($expected, ProxyHelper::generateLazyProxy($class));
    }

    public function testCannotGenerateGhostForStringMagicGet()
    {
        $this->expectException(LogicException::class);
        ProxyHelper::generateLazyGhost(new \ReflectionClass(StringMagicGetClass::class));
    }

    public function testNullStandaloneReturnType()
    {
        self::assertStringContainsString(
            'public function foo(): null',
            ProxyHelper::generateLazyProxy(new \ReflectionClass(Php82NullStandaloneReturnType::class))
        );
    }
}
