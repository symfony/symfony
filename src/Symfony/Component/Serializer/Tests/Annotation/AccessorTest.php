<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Annotation;

use Symfony\Component\Serializer\Annotation\Accessor;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class AccessorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testNoParameter()
    {
        new Accessor(array());
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameter()
    {
        new Accessor(array('value' => 'Foobar'));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidSetterParameter()
    {
        new Accessor(array('setter' => new \stdClass()));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidGetterParameter()
    {
        new Accessor(array('getter' => array()));
    }

    public function testAccessorParameters()
    {
        $accessor = new Accessor(array('setter' => 'Foo', 'getter' => 'Bar'));
        $this->assertEquals('Bar', $accessor->getGetter());
        $this->assertEquals('Foo', $accessor->getSetter());
    }
}
