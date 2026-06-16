<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Adapter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AdapterFactoryInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\LazyDsnAdapter;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;

class LazyDsnAdapterTest extends TestCase
{
    public function testTheInnerAdapterIsBuiltOnceOnFirstUseAndReused()
    {
        $factory = $this->createCountingFactory(new ArrayAdapter());
        $adapter = new LazyDsnAdapter('ns', 10, $factory, 'redis://localhost');

        $this->assertSame(0, $factory->calls, 'the inner adapter is not built at construction time');

        $adapter->save($adapter->getItem('foo')->set('bar'));
        $this->assertSame(1, $factory->calls);

        $this->assertTrue($adapter->hasItem('foo'));
        $this->assertSame(1, $factory->calls, 'the built adapter is reused across calls');
    }

    public function testCreateAdapterReceivesTheConstructorArguments()
    {
        $marshaller = $this->createStub(MarshallerInterface::class);
        $factory = $this->createMock(AdapterFactoryInterface::class);
        $factory->expects($this->once())
            ->method('createAdapter')
            ->with('redis://localhost', 'ns', 10, $marshaller)
            ->willReturn(new ArrayAdapter());

        $adapter = new LazyDsnAdapter('ns', 10, $factory, 'redis://localhost', $marshaller);
        $adapter->hasItem('foo');
    }

    public function testResetDoesNotBuildAnUnusedAdapter()
    {
        $factory = $this->createCountingFactory(new ArrayAdapter());
        $adapter = new LazyDsnAdapter('ns', 0, $factory, 'redis://localhost');

        $adapter->reset();

        $this->assertSame(0, $factory->calls);
    }

    private function createCountingFactory(AdapterInterface $inner): AdapterFactoryInterface
    {
        return new class($inner) implements AdapterFactoryInterface {
            public int $calls = 0;

            public function __construct(private readonly AdapterInterface $inner)
            {
            }

            public function createAdapter(string $dsn, string $namespace = '', int $defaultLifetime = 0, ?MarshallerInterface $marshaller = null): AdapterInterface
            {
                ++$this->calls;

                return $this->inner;
            }

            public function supports(string $dsn): bool
            {
                return true;
            }
        };
    }
}
