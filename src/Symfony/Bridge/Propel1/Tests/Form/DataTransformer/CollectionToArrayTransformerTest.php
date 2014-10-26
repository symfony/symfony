<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Propel1\Tests\Form\DataTransformer;

use \PropelObjectCollection;
use Symfony\Bridge\Propel1\Form\DataTransformer\CollectionToArrayTransformer;
use Symfony\Bridge\Propel1\Tests\Propel1TestCase;

class CollectionToArrayTransformerTest extends Propel1TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new CollectionToArrayTransformer();
    }

    public function testTransform()
    {
        $result = $this->transformer->transform(new PropelObjectCollection());

        $this->assertTrue(is_array($result));
        $this->assertCount(0, $result);
    }

    public function testTransformWithNull()
    {
        $result = $this->transformer->transform(null);

        $this->assertTrue(is_array($result));
        $this->assertCount(0, $result);
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testTransformThrowsExceptionIfNotPropelObjectCollection()
    {
        $this->transformer->transform(new DummyObject());
    }

    public function testTransformWithData()
    {
        $coll = new PropelObjectCollection();
        $coll->setData(array('foo', 'bar'));

        $result = $this->transformer->transform($coll);

        $this->assertTrue(is_array($result));
        $this->assertCount(2, $result);
        $this->assertEquals('foo', $result[0]);
        $this->assertEquals('bar', $result[1]);
    }

    public function testReverseTransformWithNull()
    {
        $result = $this->transformer->reverseTransform(null);

        $this->assertInstanceOf('\PropelObjectCollection', $result);
        $this->assertCount(0, $result->getData());
    }

    public function testReverseTransformWithEmptyString()
    {
        $result = $this->transformer->reverseTransform('');

        $this->assertInstanceOf('\PropelObjectCollection', $result);
        $this->assertCount(0, $result->getData());
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\TransformationFailedException
     */
    public function testReverseTransformThrowsExceptionIfNotArray()
    {
        $this->transformer->reverseTransform(new DummyObject());
    }

    public function testReverseTransformWithData()
    {
        $inputData = array('foo', 'bar');

        $result = $this->transformer->reverseTransform($inputData);
        $data = $result->getData();

        $this->assertInstanceOf('\PropelObjectCollection', $result);

        $this->assertTrue(is_array($data));
        $this->assertCount(2, $data);
        $this->assertEquals('foo', $data[0]);
        $this->assertEquals('bar', $data[1]);
        $this->assertsame($inputData, $data);
    }
}

class DummyObject
{
}
