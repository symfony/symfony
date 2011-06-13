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
    /**
     * @expectedException \UnexpectedValueException
     */
    public function testNormalizeNoMatch()
    {
        $this->serializer = new Serializer(array($this->getMock('Symfony\Component\Serializer\Normalizer\CustomNormalizer')));
        $this->serializer->normalize(new \stdClass, 'xml');
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testDenormalizeNoMatch()
    {
        $this->serializer = new Serializer(array($this->getMock('Symfony\Component\Serializer\Normalizer\CustomNormalizer')));
        $this->serializer->denormalize('foo', 'stdClass');
    }

    public function testSerializeScalar()
    {
        $this->serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $result = $this->serializer->serialize('foo', 'json');
        $this->assertEquals('"foo"', $result);
    }

    public function testSerializeArrayOfScalars()
    {
        $this->serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('foo', array(5, 3));
        $result = $this->serializer->serialize($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testEncode()
    {
        $this->serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('foo', array(5, 3));
        $result = $this->serializer->encode($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testDecode()
    {
        $this->serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('foo', array(5, 3));
        $result = $this->serializer->decode(json_encode($data), 'json');
        $this->assertEquals($data, $result);
    }
}
