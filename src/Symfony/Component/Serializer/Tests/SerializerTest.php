<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests;

use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\TraversableDummy;
use Symfony\Component\Serializer\Tests\Fixtures\NormalizableTraversableDummy;
use Symfony\Component\Serializer\Tests\Normalizer\TestNormalizer;
use Symfony\Component\Serializer\Tests\Normalizer\TestDenormalizer;

class SerializerTest extends \PHPUnit_Framework_TestCase
{
    public function testInterface()
    {
        $serializer = new Serializer();

        $this->assertInstanceOf('Symfony\Component\Serializer\SerializerInterface', $serializer);
        $this->assertInstanceOf('Symfony\Component\Serializer\Normalizer\NormalizerInterface', $serializer);
        $this->assertInstanceOf('Symfony\Component\Serializer\Normalizer\DenormalizerInterface', $serializer);
        $this->assertInstanceOf('Symfony\Component\Serializer\Encoder\EncoderInterface', $serializer);
        $this->assertInstanceOf('Symfony\Component\Serializer\Encoder\DecoderInterface', $serializer);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testNormalizeNoMatch()
    {
        $this->serializer = new Serializer(array($this->getMock('Symfony\Component\Serializer\Normalizer\CustomNormalizer')));
        $this->serializer->normalize(new \stdClass(), 'xml');
    }

    public function testNormalizeTraversable()
    {
        $this->serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $result = $this->serializer->serialize(new TraversableDummy(), 'json');
        $this->assertEquals('{"foo":"foo","bar":"bar"}', $result);
    }

    public function testNormalizeGivesPriorityToInterfaceOverTraversable()
    {
        $this->serializer = new Serializer(array(new CustomNormalizer()), array('json' => new JsonEncoder()));
        $result = $this->serializer->serialize(new NormalizableTraversableDummy(), 'json');
        $this->assertEquals('{"foo":"normalizedFoo","bar":"normalizedBar"}', $result);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testNormalizeOnDenormalizer()
    {
        $this->serializer = new Serializer(array(new TestDenormalizer()), array());
        $this->assertTrue($this->serializer->normalize(new \stdClass(), 'json'));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDenormalizeNoMatch()
    {
        $this->serializer = new Serializer(array($this->getMock('Symfony\Component\Serializer\Normalizer\CustomNormalizer')));
        $this->serializer->denormalize('foo', 'stdClass');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDenormalizeOnNormalizer()
    {
        $this->serializer = new Serializer(array(new TestNormalizer()), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->assertTrue($this->serializer->denormalize(json_encode($data), 'stdClass', 'json'));
    }

    public function testCustomNormalizerCanNormalizeCollectionsAndScalar()
    {
        $this->serializer = new Serializer(array(new TestNormalizer()), array());
        $this->assertNull($this->serializer->normalize(array('a', 'b')));
        $this->assertNull($this->serializer->normalize(new \ArrayObject(array('c', 'd'))));
        $this->assertNull($this->serializer->normalize(array()));
        $this->assertNull($this->serializer->normalize('test'));
    }

    public function testSerialize()
    {
        $this->serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $result = $this->serializer->serialize(Model::fromArray($data), 'json');
        $this->assertEquals(json_encode($data), $result);
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

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testSerializeNoEncoder()
    {
        $this->serializer = new Serializer(array(), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->serializer->serialize($data, 'json');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\LogicException
     */
    public function testSerializeNoNormalizer()
    {
        $this->serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->serializer->serialize(Model::fromArray($data), 'json');
    }

    public function testDeserialize()
    {
        $this->serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $result = $this->serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
        $this->assertEquals($data, $result->toArray());
    }

    public function testDeserializeUseCache()
    {
        $this->serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
        $data = array('title' => 'bar', 'numbers' => array(2, 8));
        $result = $this->serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
        $this->assertEquals($data, $result->toArray());
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\LogicException
     */
    public function testDeserializeNoNormalizer()
    {
        $this->serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDeserializeWrongNormalizer()
    {
        $this->serializer = new Serializer(array(new CustomNormalizer()), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDeserializeNoEncoder()
    {
        $this->serializer = new Serializer(array(), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
    }

    public function testDeserializeSupported()
    {
        $this->serializer = new Serializer(array(new GetSetMethodNormalizer()), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->assertTrue($this->serializer->supportsDenormalization(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json'));
    }

    public function testDeserializeNotSupported()
    {
        $this->serializer = new Serializer(array(new GetSetMethodNormalizer()), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->assertFalse($this->serializer->supportsDenormalization(json_encode($data), 'stdClass', 'json'));
    }

    public function testDeserializeNotSupportedMissing()
    {
        $this->serializer = new Serializer(array(), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->assertFalse($this->serializer->supportsDenormalization(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json'));
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

    public function testSupportsArrayDeserialization()
    {
        $serializer = new Serializer(
            array(
                new GetSetMethodNormalizer(),
                new PropertyNormalizer(),
                new ObjectNormalizer(),
                new CustomNormalizer(),
                new ArrayDenormalizer(),
            ),
            array(
                'json' => new JsonEncoder(),
            )
        );

        $this->assertTrue(
            $serializer->supportsDenormalization(array(), __NAMESPACE__.'\Model[]', 'json')
        );
    }

    public function testDeserializeArray()
    {
        $jsonData = '[{"title":"foo","numbers":[5,3]},{"title":"bar","numbers":[2,8]}]';

        $expectedData = array(
            Model::fromArray(array('title' => 'foo', 'numbers' => array(5, 3))),
            Model::fromArray(array('title' => 'bar', 'numbers' => array(2, 8))),
        );

        $serializer = new Serializer(
            array(
                new GetSetMethodNormalizer(),
                new ArrayDenormalizer(),
            ),
            array(
                'json' => new JsonEncoder(),
            )
        );

        $this->assertEquals(
            $expectedData,
            $serializer->deserialize($jsonData, __NAMESPACE__.'\Model[]', 'json')
        );
    }
}

class Model
{
    private $title;
    private $numbers;

    public static function fromArray($array)
    {
        $model = new self();
        if (isset($array['title'])) {
            $model->setTitle($array['title']);
        }
        if (isset($array['numbers'])) {
            $model->setNumbers($array['numbers']);
        }

        return $model;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getNumbers()
    {
        return $this->numbers;
    }

    public function setNumbers($numbers)
    {
        $this->numbers = $numbers;
    }

    public function toArray()
    {
        return array('title' => $this->title, 'numbers' => $this->numbers);
    }
}
