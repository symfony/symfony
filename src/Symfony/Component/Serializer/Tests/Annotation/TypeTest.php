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

use Symfony\Component\Serializer\Annotation\Type;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class TypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testNoParameter()
    {
        new Type(array());
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameterType()
    {
        new Type(array('value' => new \stdClass()));
    }

    public function testType()
    {
        $type = new Type(array('value' => 'Foobar'));
        $this->assertEquals('Foobar', $type->getType());
    }
}
