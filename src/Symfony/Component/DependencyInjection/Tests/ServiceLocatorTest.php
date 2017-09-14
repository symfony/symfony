<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ServiceLocator;

class ServiceLocatorTest extends TestCase
{
    public function testHas()
    {
        $locator = new ServiceLocator(array(
            'foo' => function () { return 'bar'; },
            'bar' => function () { return 'baz'; },
            function () { return 'dummy'; },
        ));

        $this->assertTrue($locator->has('foo'));
        $this->assertTrue($locator->has('bar'));
        $this->assertFalse($locator->has('dummy'));
    }

    public function testGet()
    {
        $locator = new ServiceLocator(array(
            'foo' => function () { return 'bar'; },
            'bar' => function () { return 'baz'; },
        ));

        $this->assertSame('bar', $locator->get('foo'));
        $this->assertSame('baz', $locator->get('bar'));
    }

    public function testGetDoesNotMemoize()
    {
        $i = 0;
        $locator = new ServiceLocator(array(
            'foo' => function () use (&$i) {
                ++$i;

                return 'bar';
            },
        ));

        $this->assertSame('bar', $locator->get('foo'));
        $this->assertSame('bar', $locator->get('foo'));
        $this->assertSame(2, $i);
    }

    /**
     * @expectedException        \Psr\Container\NotFoundExceptionInterface
     * @expectedExceptionMessage You have requested a non-existent service "dummy". Did you mean one of these: "foo", "bar"?
     */
    public function testGetThrowsOnUndefinedService()
    {
        $locator = new ServiceLocator(array(
            'foo' => function () { return 'bar'; },
            'bar' => function () { return 'baz'; },
        ));

        try {
            $locator->get('dummy');
        } catch (ServiceNotFoundException $e) {
            $this->assertSame(array('foo', 'bar'), $e->getAlternatives());

            throw $e;
        }
    }

    public function testInvoke()
    {
        $locator = new ServiceLocator(array(
            'foo' => function () { return 'bar'; },
            'bar' => function () { return 'baz'; },
        ));

        $this->assertSame('bar', $locator('foo'));
        $this->assertSame('baz', $locator('bar'));
        $this->assertNull($locator('dummy'), '->__invoke() should return null on invalid service');
    }
}
