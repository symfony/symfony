<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests;

use Symfony\Bundle\AsseticBundle\FilterManager;

class FilterManagerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }
    }

    public function testGet()
    {
        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface');
        $filter = $this->getMock('Assetic\\Filter\\FilterInterface');

        $container->expects($this->exactly(2))
            ->method('get')
            ->with('assetic.filter.bar')
            ->will($this->returnValue($filter));

        $fm = new FilterManager($container, array('foo' => 'assetic.filter.bar'));

        $this->assertSame($filter, $fm->get('foo'), '->get() loads the filter from the container');
        $this->assertSame($filter, $fm->get('foo'), '->get() loads the filter from the container');
    }

    public function testHas()
    {
        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface');

        $fm = new FilterManager($container, array('foo' => 'assetic.filter.bar'));
        $this->assertTrue($fm->has('foo'), '->has() returns true for lazily mapped filters');
    }

    public function testGetNames()
    {
        $container = $this->getMock('Symfony\\Component\\DependencyInjection\\ContainerInterface');
        $filter = $this->getMock('Assetic\\Filter\\FilterInterface');

        $fm = new FilterManager($container, array('foo' => 'assetic.filter.bar'));
        $fm->set('bar', $filter);

        $this->assertEquals(array('foo', 'bar'), $fm->getNames(), '->getNames() returns all lazy and normal filter names');
    }
}
