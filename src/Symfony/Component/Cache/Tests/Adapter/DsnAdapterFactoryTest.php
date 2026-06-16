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
use Symfony\Component\Cache\Adapter\DsnAdapterFactory;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

class DsnAdapterFactoryTest extends TestCase
{
    public function testDelegatesToTheFirstSupportingFactory()
    {
        $expected = new ArrayAdapter();
        $factory = new DsnAdapterFactory([
            $this->createFactory(false),
            $this->createFactory(true, $expected),
            $this->createFactory(true, new ArrayAdapter()),
        ]);

        $this->assertTrue($factory->supports('redis://localhost'));
        $this->assertSame($expected, $factory->createAdapter('redis://localhost'));
    }

    public function testThrowsWhenNoFactorySupportsTheDsn()
    {
        $factory = new DsnAdapterFactory([$this->createFactory(false)]);

        $this->assertFalse($factory->supports('foo://bar'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No cache adapter supports the DSN scheme "foo://".');

        $factory->createAdapter('foo://bar');
    }

    private function createFactory(bool $supports, ?AdapterInterface $adapter = null): AdapterFactoryInterface
    {
        $factory = $this->createStub(AdapterFactoryInterface::class);
        $factory->method('supports')->willReturn($supports);
        $factory->method('createAdapter')->willReturn($adapter ?? new ArrayAdapter());

        return $factory;
    }
}
