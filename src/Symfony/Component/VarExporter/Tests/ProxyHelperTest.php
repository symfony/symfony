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
use Symfony\Component\VarExporter\ProxyHelper;
use Symfony\Component\VarExporter\Tests\Fixtures\LazyProxy\StringMagicGetClass;

class ProxyHelperTest extends TestCase
{
    /**
     * @dataProvider provideExportSignature
     */
    public function testExportSignature(string $expected, \ReflectionMethod $method)
    {
        $this->assertSame($expected, ProxyHelper::exportSignature($method));
    }

    public function provideExportSignature()
    {
        $methods = (new \ReflectionClass(TestForProxyHelper::class))->getMethods();
        $source = file(__FILE__);

        foreach ($methods as $method) {
            $expected = substr($source[$method->getStartLine() - 1], $method->isAbstract() ? 13 : 4, -(1 + $method->isAbstract()));
            $expected = str_replace(['.', ' .  .  . ', '"', '\0'], [' . ', '...', "'", '\'."\0".\''], $expected);
            $expected = str_replace('Bar', '\\'.Bar::class, $expected);
            $expected = str_replace('self', '\\'.TestForProxyHelper::class, $expected);

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
            use \Symfony\Component\VarExporter\LazyProxyTrait;

            private int $lazyObjectId;
            private parent $lazyObjectReal;

            private const LAZY_OBJECT_PROPERTY_SCOPES = [
                'lazyObjectReal' => [self::class, 'lazyObjectReal', null],
                "\0".self::class."\0lazyObjectReal" => [self::class, 'lazyObjectReal', null],
            ];

            public function foo1(): ?\Symfony\Component\VarExporter\Tests\Bar
            {
                if (isset($this->lazyObjectReal)) {
                    return $this->lazyObjectReal->foo1(...\func_get_args());
                }

                return parent::foo1(...\func_get_args());
            }

            public function foo4(\Symfony\Component\VarExporter\Tests\Bar|string $b): void
            {
                if (isset($this->lazyObjectReal)) {
                    $this->lazyObjectReal->foo4(...\func_get_args());
                } else {
                    parent::foo4(...\func_get_args());
                }
            }

            protected function foo7()
            {
                if (isset($this->lazyObjectReal)) {
                    return $this->lazyObjectReal->foo7(...\func_get_args());
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

            private int $lazyObjectId;
            private \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface1&\Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2 $lazyObjectReal;

            public function initializeLazyObject(): \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface1&\Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2
            {
                if (isset($this->lazyObjectReal)) {
                    return $this->lazyObjectReal;
                }

                return $this;
            }

            public function foo1(): ?\Symfony\Component\VarExporter\Tests\Bar
            {
                if (isset($this->lazyObjectReal)) {
                    return $this->lazyObjectReal->foo1(...\func_get_args());
                }

                return throw new \BadMethodCallException('Cannot forward abstract method "Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface1::foo1()".');
            }

            public function foo2(?\Symfony\Component\VarExporter\Tests\Bar $b): \Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2
            {
                if (isset($this->lazyObjectReal)) {
                    return $this->lazyObjectReal->foo2(...\func_get_args());
                }

                return throw new \BadMethodCallException('Cannot forward abstract method "Symfony\Component\VarExporter\Tests\TestForProxyHelperInterface2::foo2()".');
            }
        }

        // Help opcache.preload discover always-needed symbols
        class_exists(\Symfony\Component\VarExporter\Internal\Hydrator::class);
        class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectRegistry::class);
        class_exists(\Symfony\Component\VarExporter\Internal\LazyObjectState::class);

        EOPHP;

        $this->assertSame($expected, ProxyHelper::generateLazyProxy(null, [new \ReflectionClass(TestForProxyHelperInterface1::class), new \ReflectionClass(TestForProxyHelperInterface2::class)]));
    }

    public function testAttributes()
    {
        $expected = <<<'EOPHP'

            public function foo(#[\SensitiveParameter] $a): int
            {
                if (isset($this->lazyObjectReal)) {
                    return $this->lazyObjectReal->foo(...\func_get_args());
                }

                return parent::foo(...\func_get_args());
            }
        }

        EOPHP;

        $class = new \ReflectionClass(new class() {
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
}

abstract class TestForProxyHelper
{
    public function foo1(): ?Bar
    {
    }

    public function foo2(?Bar $b): ?self
    {
    }

    public function &foo3(Bar &$b, string &...$c)
    {
    }

    public function foo4(Bar|string $b): void
    {
    }

    public function foo5($b = new \stdClass([0 => 123]).Bar.Bar::BAR."a\0b")
    {
    }

    protected function foo6($b = null): never
    {
    }

    abstract protected function foo7();

    public static function foo8()
    {
    }
}

interface TestForProxyHelperInterface1
{
    public function foo1(): ?Bar;
}

interface TestForProxyHelperInterface2
{
    public function foo2(?Bar $b): self;
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
