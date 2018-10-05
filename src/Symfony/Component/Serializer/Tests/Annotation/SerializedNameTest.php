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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Annotation\SerializedName;

/**
 * @author Fabien Bourigault <bourigaultfabien@gmail.com>
 */
class SerializedNameTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage Parameter of annotation "Symfony\Component\Serializer\Annotation\SerializedName" should be set.
     */
    public function testNotSetSerializedNameParameter()
    {
        new SerializedName(array());
    }

    public function provideInvalidValues()
    {
        return array(
            array(''),
            array(0),
        );
    }

    /**
     * @dataProvider provideInvalidValues
     *
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage Parameter of annotation "Symfony\Component\Serializer\Annotation\SerializedName" must be a non-empty string.
     */
    public function testNotAStringSerializedNameParameter($value)
    {
        new SerializedName(array('value' => $value));
    }

    public function testSerializedNameParameters()
    {
        $maxDepth = new SerializedName(array('value' => 'foo'));
        $this->assertEquals('foo', $maxDepth->getSerializedName());
    }
}
