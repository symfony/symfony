<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type\Loader;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Type\FormTypeInterface;
use Symfony\Component\Form\Type\Loader\ArrayTypeLoader;

class ArrayTypeLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testHasType()
    {
        $type = $this->getMock('Symfony\Component\Form\Type\FormTypeInterface');
        $type->expects($this->once())
             ->method('getName')
             ->will($this->returnValue('foo'));

        $loader = new ArrayTypeLoader(array($type));
        $this->assertTrue($loader->hasType('foo'));
        $this->assertFalse($loader->hasType('bar'));
    }

    public function testGetType()
    {
        $type = $this->getMock('Symfony\Component\Form\Type\FormTypeInterface');
        $type->expects($this->once())
             ->method('getName')
             ->will($this->returnValue('foo'));

        $loader = new ArrayTypeLoader(array($type));
        $this->assertSame($type, $loader->getType('foo'));
        $this->assertSame($loader->getType('foo'), $loader->getType('foo'));
    }
}