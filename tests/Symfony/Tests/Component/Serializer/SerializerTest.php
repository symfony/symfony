<?php

namespace Symfony\Tests\Component\Serializer;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

class SerializerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->serializer = new Serializer();
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testNormalizeObjectNoMatch()
    {
        $this->serializer->addNormalizer($this->getMock('Symfony\Component\Serializer\Normalizer\CustomNormalizer'));
        $this->serializer->normalizeObject(new \stdClass, 'xml');
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testDenormalizeObjectNoMatch()
    {
        $this->serializer->addNormalizer($this->getMock('Symfony\Component\Serializer\Normalizer\CustomNormalizer'));
        $this->serializer->denormalizeObject('foo', 'stdClass');
    }

    public function testSerializeScalar()
    {
        $this->serializer->setEncoder('json', new JsonEncoder());
        $result = $this->serializer->serialize('foo', 'json');
        $this->assertEquals('"foo"', $result);
    }

    public function testSerializeArrayOfScalars()
    {
        $this->serializer->setEncoder('json', new JsonEncoder());
        $data = array('foo', array(5, 3));
        $result = $this->serializer->serialize($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testEncode()
    {
        $this->serializer->setEncoder('json', new JsonEncoder());
        $data = array('foo', array(5, 3));
        $result = $this->serializer->encode($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testDecode()
    {
        $this->serializer->setDecoder('json', new JsonEncoder());
        $data = array('foo', array(5, 3));
        $result = $this->serializer->decode(json_encode($data), 'json');
        $this->assertEquals($data, $result);
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testNormalizeNoMatchObject()
    {
        $this->serializer->addNormalizer($this->getMock('Symfony\Component\Serializer\Normalizer\CustomNormalizer'));
        $this->serializer->normalizeObject(new \stdClass, 'xml');
    }
}
