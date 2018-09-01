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

use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class SerializedNameTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testNoParameter()
    {
        new SerializedName(array());
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     */
    public function testInvalidParameterType()
    {
        new SerializedName(array('value' => new \stdClass()));
    }

    public function testSerializedName()
    {
        $serializedName = new SerializedName(array('value' => 'Foobar'));
        $this->assertEquals('Foobar', $serializedName->getName());
    }
}
