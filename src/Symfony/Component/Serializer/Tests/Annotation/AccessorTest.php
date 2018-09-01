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

use Symfony\Component\Serializer\Annotation\Methods;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class AccessorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testNoParameter()
    {
        new Methods(array());
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameter()
    {
        new Methods(array('value' => 'Foobar'));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidSetterParameter()
    {
        new Methods(array('setter' => new \stdClass()));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidGetterParameter()
    {
        new Methods(array('getter' => array()));
    }

    public function testAccessorParameters()
    {
        $accessor = new Methods(array('mutator' => 'Foo', 'accessor' => 'Bar'));
        $this->assertEquals('Bar', $accessor->getAccessor());
        $this->assertEquals('Foo', $accessor->getMutator());
    }
}
