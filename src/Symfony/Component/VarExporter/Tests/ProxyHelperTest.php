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
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\Hooked;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\Php82NullStandaloneReturnType;

/**
 * @requires PHP 8.4
 */
class ProxyHelperTest extends TestCase
{
    /**
     * @dataProvider provideExportSignature
     */
    public function testExportSignature(string $expected, \ReflectionMethod $method)
    {
        $this->assertSame($expected, ProxyHelper::exportSignature($method));
    }

    public static function provideExportSignature()
    {
        $methods = (new \ReflectionClass(TestForProxyHelper::class))->getMethods();
        $source = file(__FILE__);

        foreach ($methods as $method) {
            $expected = substr($source[$method->getStartLine() - 1], $method->isAbstract() ? 13 : 4, -(1 + $method->isAbstract()));
            $expected = str_replace(['.', ' .  .  . ', '\'$a\', \'$a\n\', "\$a\n"'], [' . ', '...', '\'$a\', "\$a\\\n", "\$a\n"'], $expected);
            $expected = str_replace('Bar', '\\'.Bar::class, $expected);
            $expected = str_replace('self', '\\'.TestForProxyHelper::class, $expected);
            $expected = str_replace('= [namespace\M_PI, new M_PI()]', '= [\M_PI, new \Symfony\Component\VarExporter\Tests\M_PI()]', $expected);

            yield [$expected, $method];
        }
    }

    public function testExportSignatureFQ()
    {
        $expected = <<<'EOPHP'
        public function bar($a = \Symfony\Component\VarExporter\Tests\Bar::BAZ,
        $b = new \Symfony\Component\VarExporter\Tests\Bar(\Symfony\Component\VarExporter\Tests\Bar::BAZ, bar: \Symfony\Component\VarExporter\Tests\Bar::BAZ),
        $c = new \stdClass(),
        $d = new \Symfony\Component\VarExporter\Tests\TestSignatureFQ(),
        $e = new \Symfony\Component\VarExporter\Tests\Bar(),
        $f = new \Symfony\Component\VarExporter\Tests\Qux(),
        $g = new \Symfony\Component\VarExporter\Tests\Qux(),
        $i = new \Qux(),
        $j = \stdClass::BAZ,
        $k = \Symfony\Component\VarExporter\Tests\Bar)
        EOPHP;

        $this->assertSame($expected, str_replace(', $', ",\n$", ProxyHelper::exportSignature(new \ReflectionMethod(TestSignatureFQ::class, 'bar'))));
    }

    public function testGenerateLazyProxy()
    {
        $expected = <<<'EOPHP'
         extends \Symfony\Component\VarExporter\Tests\TestForProxyHelper implements \Symfony\Component\VarExporter\LazyObjectInterface
        {
            use \Symfony\Component\VarExporter\Internal\LazyDecoratorTrait;

            private const LAZY_OBJECT_PROPERTY_SCOPES = [];

            public function foo1(): ?\Symfony\Component\VarExporter\Tests\Bar
            {
                return $this->lazyObjectState->realInstance->foo1(...\func_get_args());
            }

            public function foo2(?\Symfony\Component\VarExporter\Tests\Bar $b, ...$d): ?\Symfony\Component\VarExporter\Tests\TestForProxyHelper
            {
                ${0} = $this->lazyObjectState->realInstance;
                ${1} = ${0}->foo2(...\func_get_args());

                return match (true) {
                    ${1} === ${0} => $this,
                    !${1} instanceof ${0} || !${0} instanceof ${1} => ${1},
                    null !== $this->lazyObjectState->cloneInstance =& ${1} => clone $this,
                };
            }

            public function &foo3(\Symfony\Component\VarExporter\Tests\Bar &$b, string &...$c)
            {
                return $this->lazyObjectState->realInstance->foo3($b, ...$c);
            }

            public function foo4(\Symfony\Component\VarExporter\Tests\Bar|string $b, &$d): void
            {
                $this->lazyObjectState->realInstance->foo4($b, $d, ...\array_slice(\func_get_args(), 2));
            }

            public function foo5($b = new \stdClass([0 => 123]) . \Symfony\Component\VarExporter\Tests\Bar . \Symfony\Component\VarExporter\Tests\Bar::BAR . "a\0b")
            {
                ${0} = $this->lazyObjectState->realInstance;
                ${1} = ${0}->foo5(...\func_get_args());

                return match (true) {
                    ${1} === ${0} => $this,
                    !${1} instanceof ${0} || !${0} instanceof ${1} => ${1},
                    null !== $this->lazyObjectState->cloneInstance =& ${1} => clone $this,
                };
            }

            protected function foo6($b = null, $c = \PHP_EOL, $d = [\PHP_EOL], $e = [false, true, null]): never
            {
                $this->lazyObjectState->realInstance->foo6(...\func_get_args());
            }

            protected function foo7()
            {
                ${0} = $this->lazyObjectState->realInstance;
                ${1} = ${0}->foo7(...\func_get_args());

                return match (true) {
                    ${1} === ${0} => $this,
                    !${1} instanceof ${0} || !${0} instanceof ${1} => ${1},
                    null !== $this->lazyObjectState->cloneInstance =& ${1} => clone $this,
                };
            }

            public function foo9($a = \Symfony\Component\VarExporter\Tests\TestForProxyHelper::BOB, $b = ['$a', "\$a\\n", "\$a\n"], $c = ['$a', "\$a\\n", "\$a\n", new \stdClass()])
            {
                ${0} = $this->lazyObjectState->realInstance;
                ${1} = ${0}->foo9(...\func_get_args());

                return match (true) {
                    ${1} === ${0} => $this,
                    !${1} instanceof ${0} || !${0} instanceof ${1} => ${1},
                    null !== $this->lazyObjectState->cloneInstance =& ${1} => clone $this,
                };
            }

            public function foo10($a = [\M_PI, new \Symfony\Component\VarExporter\Tests\M_PI()])
            {
                ${0} = $this->lazyObjectState->realInstance;
                ${1} = ${0}->foo10(...\func_get_args());

                return match (true) {
                    ${1} === ${0} => $this,
                    !${1} instanceof ${0} || !${0} instanceof ${1} => ${1},
                    null !== $this->lazyObjectState->cloneInstance =& ${1} => clone $this,
                };
            }
        }

        // Help opcache.preload discover always-needed symbols
        class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
        class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);

        EOPHP;

        $this->assertSame($expected, ProxyHelper::generateLazyProxy(new \ReflectionClass(TestForProxyHelper::class)));
    }

    public function testGenerateLazyProxyForInterfaces()
    {
        $expected = <<<'EOPHP'
         implements \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface1, \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2, \Symfony\Component\VarExporter\LazyObjectInterface
        {
            use \Symfony\Component\VarExporter\Internal\LazyDecoratorTrait;

            private const LAZY_OBJECT_PROPERTY_SCOPES = [];

            public function initializeLazyObject(): \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface1&\Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2
            {
                return $this->lazyObjectState->realInstance;
            }

            public function foo1(): ?\Symfony\Component\VarExporter\Tests\Bar
            {
                return $this->lazyObjectState->realInstance->foo1(...\func_get_args());
            }

            public function foo2(?\Symfony\Component\VarExporter\Tests\Bar $b, ...$d): \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2
            {
                ${0} = $this->lazyObjectState->realInstance;
                ${1} = ${0}->foo2(...\func_get_args());

                return match (true) {
                    ${1} === ${0} => $this,
                    !${1} instanceof ${0} || !${0} instanceof ${1} => ${1},
                    null !== $this->lazyObjectState->cloneInstance =& ${1} => clone $this,
                };
            }

            public static function foo3(): string
            {
                throw new \BadMethodCallException('Cannot forward abstract method "Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2::foo3()".');
            }
        }

        // Help opcache.preload discover always-needed symbols
        class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
        class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);

        EOPHP;

        $this->assertSame($expected, ProxyHelper::generateLazyProxy(null, [new \ReflectionClass(TestForProxyHelperInterface1::class), new \ReflectionClass(TestForProxyHelperInterface2::class)]));
    }

    /**
     * @dataProvider classWithUnserializeMagicMethodProvider
     */
    public function testGenerateLazyProxyForClassWithUnserializeMagicMethod(object $obj, string $expected)
    {
        $this->assertStringContainsString($expected, ProxyHelper::generateLazyProxy(new \ReflectionClass($obj::class)));
    }

    public static function classWithUnserializeMagicMethodProvider(): iterable
    {
        yield 'not type hinted __unserialize method' => [new class extends \stdClass {
            public function __unserialize($array): void
            {
            }
        }, <<<'EOPHP'
        implements \Symfony\Component\VarExporter\LazyObjectInterface
        {
            use \Symfony\Component\VarExporter\Internal\LazyDecoratorTrait {
                __unserialize as private __doUnserialize;
            }

            private const LAZY_OBJECT_PROPERTY_SCOPES = [];

            public function __unserialize($data): void
            {
                $this->__doUnserialize($data);
            }
        }
        EOPHP];

        yield 'type hinted __unserialize method' => [new class extends \stdClass {
            public function __unserialize(array $array): void
            {
            }
        }, <<<'EOPHP'
        implements \Symfony\Component\VarExporter\LazyObjectInterface
        {
            use \Symfony\Component\VarExporter\Internal\LazyDecoratorTrait;

            private const LAZY_OBJECT_PROPERTY_SCOPES = [];
        }
        EOPHP];
    }

    public function testAttributes()
    {
        $expected = <<<'EOPHP'

            public function foo(#[\SensitiveParameter] $a): int
            {
                return $this->lazyObjectState->realInstance->foo(...\func_get_args());
            }
        }

        EOPHP;

        $class = new \ReflectionClass(new class extends \stdClass {
            #[SomeAttribute]
            public function foo(#[\SensitiveParameter, AnotherAttribute] $a): int
            {
            }
        });

        $this->assertStringContainsString($expected, ProxyHelper::generateLazyProxy($class));
    }

    public function testNullStandaloneReturnType()
    {
        self::assertStringContainsString(
            'public function foo(): null',
            ProxyHelper::generateLazyProxy(new \ReflectionClass(Php82NullStandaloneReturnType::class))
        );
    }

    /**
     * @requires PHP 8.4
     */
    public function testPropertyHooks()
    {
        $proxyCode = ProxyHelper::generateLazyProxy(new \ReflectionClass(Hooked::class));
        self::assertStringContainsString('public int $notBacked {', $proxyCode);
        self::assertStringContainsString('public int $backed {', $proxyCode);
    }
}

abstract class TestForProxyHelper
{
    public function foo1(): ?Bar
    {
    }

    public function foo2(?Bar $b, ...$d): ?self
    {
    }

    public function &foo3(Bar &$b, string &...$c)
    {
    }

    public function foo4(Bar|string $b, &$d): void
    {
    }

    public function foo5($b = new \stdClass([0 => 123]).Bar.Bar::BAR."a\0b")
    {
    }

    protected function foo6($b = null, $c = \PHP_EOL, $d = [\PHP_EOL], $e = [false, true, null]): never
    {
    }

    abstract protected function foo7();

    public static function foo8()
    {
    }

    public function foo9($a = self::BOB, $b = ['$a', '$a\n', "\$a\n"], $c = ['$a', '$a\n', "\$a\n", new \stdClass()])
    {
    }

    public function foo10($a = [namespace\M_PI, new M_PI()])
    {
    }
}

interface TestForProxyHelperInterface1
{
    public function foo1(): ?Bar;
}

interface TestForProxyHelperInterface2
{
    public function foo2(?Bar $b, ...$d): self;

    public static function foo3(): string;
}

class TestSignatureFQ extends \stdClass
{
    public function bar(
        $a = Bar::BAZ,
        $b = new Bar(Bar::BAZ, bar: Bar::BAZ),
        $c = new parent(),
        $d = new self(),
        $e = new namespace\Bar(),
        $f = new Qux(),
        $g = new namespace\Qux(),
        $i = new \Qux(),
        $j = parent::BAZ,
        $k = Bar,
    ) {
    }
}
