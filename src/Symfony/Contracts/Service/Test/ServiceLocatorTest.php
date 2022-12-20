<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\Service\Test;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceLocatorTrait;

abstract class ServiceLocatorTest extends TestCase
{
    /**
     * @return ContainerInterface
     */
    protected function getServiceLocator(array $factories)
    {
        return new class($factories) implements ContainerInterface {
            use ServiceLocatorTrait;
        };
    }

    public function testHas()
    {
        $locator = $this->getServiceLocator([
            'foo' => function () { return 'bar'; },
            'bar' => function () { return 'baz'; },
            function () { return 'dummy'; },
        ]);

        self::assertTrue($locator->has('foo'));
        self::assertTrue($locator->has('bar'));
        self::assertFalse($locator->has('dummy'));
    }

    public function testGet()
    {
        $locator = $this->getServiceLocator([
            'foo' => function () { return 'bar'; },
            'bar' => function () { return 'baz'; },
        ]);

        self::assertSame('bar', $locator->get('foo'));
        self::assertSame('baz', $locator->get('bar'));
    }

    public function testGetDoesNotMemoize()
    {
        $i = 0;
        $locator = $this->getServiceLocator([
            'foo' => function () use (&$i) {
                ++$i;

                return 'bar';
            },
        ]);

        self::assertSame('bar', $locator->get('foo'));
        self::assertSame('bar', $locator->get('foo'));
        self::assertSame(2, $i);
    }

    public function testThrowsOnUndefinedInternalService()
    {
        if (!self::getExpectedException()) {
            self::expectException(\Psr\Container\NotFoundExceptionInterface::class);
            self::expectExceptionMessage('The service "foo" has a dependency on a non-existent service "bar". This locator only knows about the "foo" service.');
        }
        $locator = $this->getServiceLocator([
            'foo' => function () use (&$locator) { return $locator->get('bar'); },
        ]);

        $locator->get('foo');
    }

    public function testThrowsOnCircularReference()
    {
        self::expectException(\Psr\Container\ContainerExceptionInterface::class);
        self::expectExceptionMessage('Circular reference detected for service "bar", path: "bar -> baz -> bar".');
        $locator = $this->getServiceLocator([
            'foo' => function () use (&$locator) { return $locator->get('bar'); },
            'bar' => function () use (&$locator) { return $locator->get('baz'); },
            'baz' => function () use (&$locator) { return $locator->get('bar'); },
        ]);

        $locator->get('foo');
    }
}
