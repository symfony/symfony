<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\Factory\Resource;

use Symfony\Bundle\AsseticBundle\Factory\Resource\CoalescingDirectoryResource;

class CoalescingDirectoryResourceTest extends \PHPUnit_Framework_TestCase
{
    public function testFiltering()
    {
        $dir1 = $this->getMock('Assetic\\Factory\\Resource\\IteratorResourceInterface');
        $file1a = $this->getMock('Assetic\\Factory\\Resource\\ResourceInterface');
        $file1b = $this->getMock('Assetic\\Factory\\Resource\\ResourceInterface');

        $dir2 = $this->getMock('Assetic\\Factory\\Resource\\IteratorResourceInterface');
        $file2a = $this->getMock('Assetic\\Factory\\Resource\\ResourceInterface');
        $file2c = $this->getMock('Assetic\\Factory\\Resource\\ResourceInterface');

        $dir1->expects($this->any())
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator(array($file1a, $file1b))));
        $file1a->expects($this->any())
            ->method('__toString')
            ->will($this->returnValue('FooBundle:Foo:file1.foo.bar'));
        $file1b->expects($this->any())
            ->method('__toString')
            ->will($this->returnValue('FooBundle:Foo:file2.foo.bar'));

        $dir2->expects($this->any())
            ->method('getIterator')
            ->will($this->returnValue(new \ArrayIterator(array($file2a, $file2c))));
        $file2a->expects($this->any())
            ->method('__toString')
            ->will($this->returnValue('BarBundle:Foo:file1.foo.bar'));
        $file2c->expects($this->any())
            ->method('__toString')
            ->will($this->returnValue('BarBundle:Foo:file3.foo.bar'));

        $resource = new CoalescingDirectoryResource(array($dir1, $dir2));

        $actual = array();
        foreach ($resource as $file) {
            $actual[] = (string) $file;
        }

        $expected = array(
            'FooBundle:Foo:file1.foo.bar',
            'FooBundle:Foo:file2.foo.bar',
            'BarBundle:Foo:file3.foo.bar',
        );

        $this->assertEquals($expected, $actual);
    }
}
