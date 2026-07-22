<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\LazyProxy\Instantiator;

use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\LazyProxy\Instantiator\LazyServiceInstantiator;
use Symfony\Component\DependencyInjection\Tests\Fixtures\AbstractSayClass;

class LazyServiceInstantiatorTest extends TestCase
{
    public function testInstantiateAbstractClassProxy()
    {
        $instantiator = new LazyServiceInstantiator();
        $instance = new class extends AbstractSayClass {
            public int $calls = 0;

            public function say(): string
            {
                ++$this->calls;

                return 'Hello from the abstract class!';
            }
        };

        $definition = (new Definition(AbstractSayClass::class))
            ->setLazy(true);

        $proxy = $instantiator->instantiateProxy(new Container(), $definition, 'foo', static fn () => $instance);

        $this->assertSame(0, $instance->calls);
        $this->assertSame('Hello from the abstract class!', $proxy->say());
        $this->assertSame(1, $instance->calls);
    }

    #[RequiresPhp('>=8.4.0')]
    public function testInstantiateProxyWithFactoryAndLeadingBackslashClassName()
    {
        $instantiator = new LazyServiceInstantiator();

        $definition = (new Definition('\\'.LazyProxiedTestService::class))
            ->setFactory([LazyProxiedTestService::class, 'create'])
            ->setLazy(true);

        $proxy = $instantiator->instantiateProxy(new Container(), $definition, 'foo', static fn () => LazyProxiedTestService::create());

        $this->assertInstanceOf(LazyProxiedTestService::class, $proxy);
        $this->assertSame('hello', $proxy->say());
    }
}

class LazyProxiedTestService
{
    public static function create(): self
    {
        return new self();
    }

    public function say(): string
    {
        return 'hello';
    }
}
