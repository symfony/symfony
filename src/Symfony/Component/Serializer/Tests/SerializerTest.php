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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
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

class SerializerTest extends TestCase
{
    public function testInterface(): void
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
    public function testNormalizeNoMatch(): void
    {
        $serializer = new Serializer(array($this->getMockBuilder('Symfony\Component\Serializer\Normalizer\CustomNormalizer')->getMock()));
        $serializer->normalize(new \stdClass(), 'xml');
    }

    public function testNormalizeTraversable(): void
    {
        $serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $result = $serializer->serialize(new TraversableDummy(), 'json');
        $this->assertEquals('{"foo":"foo","bar":"bar"}', $result);
    }

    public function testNormalizeGivesPriorityToInterfaceOverTraversable(): void
    {
        $serializer = new Serializer(array(new CustomNormalizer()), array('json' => new JsonEncoder()));
        $result = $serializer->serialize(new NormalizableTraversableDummy(), 'json');
        $this->assertEquals('{"foo":"normalizedFoo","bar":"normalizedBar"}', $result);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testNormalizeOnDenormalizer(): void
    {
        $serializer = new Serializer(array(new TestDenormalizer()), array());
        $this->assertTrue($serializer->normalize(new \stdClass(), 'json'));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDenormalizeNoMatch(): void
    {
        $serializer = new Serializer(array($this->getMockBuilder('Symfony\Component\Serializer\Normalizer\CustomNormalizer')->getMock()));
        $serializer->denormalize('foo', 'stdClass');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDenormalizeOnNormalizer(): void
    {
        $serializer = new Serializer(array(new TestNormalizer()), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->assertTrue($serializer->denormalize(json_encode($data), 'stdClass', 'json'));
    }

    public function testCustomNormalizerCanNormalizeCollectionsAndScalar(): void
    {
        $serializer = new Serializer(array(new TestNormalizer()), array());
        $this->assertNull($serializer->normalize(array('a', 'b')));
        $this->assertNull($serializer->normalize(new \ArrayObject(array('c', 'd'))));
        $this->assertNull($serializer->normalize(array()));
        $this->assertNull($serializer->normalize('test'));
    }

    public function testNormalizeWithSupportOnData(): void
    {
        $normalizer1 = $this->getMockBuilder('Symfony\Component\Serializer\Normalizer\NormalizerInterface')->getMock();
        $normalizer1->method('supportsNormalization')
            ->willReturnCallback(function ($data, $format) {
                return isset($data->test);
            });
        $normalizer1->method('normalize')->willReturn('test1');

        $normalizer2 = $this->getMockBuilder('Symfony\Component\Serializer\Normalizer\NormalizerInterface')->getMock();
        $normalizer2->method('supportsNormalization')
            ->willReturn(true);
        $normalizer2->method('normalize')->willReturn('test2');

        $serializer = new Serializer(array($normalizer1, $normalizer2));

        $data = new \stdClass();
        $data->test = true;
        $this->assertEquals('test1', $serializer->normalize($data));

        $this->assertEquals('test2', $serializer->normalize(new \stdClass()));
    }

    public function testDenormalizeWithSupportOnData(): void
    {
        $denormalizer1 = $this->getMockBuilder('Symfony\Component\Serializer\Normalizer\DenormalizerInterface')->getMock();
        $denormalizer1->method('supportsDenormalization')
            ->willReturnCallback(function ($data, $type, $format) {
                return isset($data['test1']);
            });
        $denormalizer1->method('denormalize')->willReturn('test1');

        $denormalizer2 = $this->getMockBuilder('Symfony\Component\Serializer\Normalizer\DenormalizerInterface')->getMock();
        $denormalizer2->method('supportsDenormalization')
            ->willReturn(true);
        $denormalizer2->method('denormalize')->willReturn('test2');

        $serializer = new Serializer(array($denormalizer1, $denormalizer2));

        $this->assertEquals('test1', $serializer->denormalize(array('test1' => true), 'test'));

        $this->assertEquals('test2', $serializer->denormalize(array(), 'test'));
    }

    public function testSerialize(): void
    {
        $serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $result = $serializer->serialize(Model::fromArray($data), 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testSerializeScalar(): void
    {
        $serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $result = $serializer->serialize('foo', 'json');
        $this->assertEquals('"foo"', $result);
    }

    public function testSerializeArrayOfScalars(): void
    {
        $serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('foo', array(5, 3));
        $result = $serializer->serialize($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testSerializeNoEncoder(): void
    {
        $serializer = new Serializer(array(), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $serializer->serialize($data, 'json');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\LogicException
     */
    public function testSerializeNoNormalizer(): void
    {
        $serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $serializer->serialize(Model::fromArray($data), 'json');
    }

    public function testDeserialize(): void
    {
        $serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $result = $serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
        $this->assertEquals($data, $result->toArray());
    }

    public function testDeserializeUseCache(): void
    {
        $serializer = new Serializer(array(new GetSetMethodNormalizer()), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
        $data = array('title' => 'bar', 'numbers' => array(2, 8));
        $result = $serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
        $this->assertEquals($data, $result->toArray());
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\LogicException
     */
    public function testDeserializeNoNormalizer(): void
    {
        $serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDeserializeWrongNormalizer(): void
    {
        $serializer = new Serializer(array(new CustomNormalizer()), array('json' => new JsonEncoder()));
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\UnexpectedValueException
     */
    public function testDeserializeNoEncoder(): void
    {
        $serializer = new Serializer(array(), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $serializer->deserialize(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json');
    }

    public function testDeserializeSupported(): void
    {
        $serializer = new Serializer(array(new GetSetMethodNormalizer()), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->assertTrue($serializer->supportsDenormalization(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json'));
    }

    public function testDeserializeNotSupported(): void
    {
        $serializer = new Serializer(array(new GetSetMethodNormalizer()), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->assertFalse($serializer->supportsDenormalization(json_encode($data), 'stdClass', 'json'));
    }

    public function testDeserializeNotSupportedMissing(): void
    {
        $serializer = new Serializer(array(), array());
        $data = array('title' => 'foo', 'numbers' => array(5, 3));
        $this->assertFalse($serializer->supportsDenormalization(json_encode($data), '\Symfony\Component\Serializer\Tests\Model', 'json'));
    }

    public function testEncode(): void
    {
        $serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('foo', array(5, 3));
        $result = $serializer->encode($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testDecode(): void
    {
        $serializer = new Serializer(array(), array('json' => new JsonEncoder()));
        $data = array('foo', array(5, 3));
        $result = $serializer->decode(json_encode($data), 'json');
        $this->assertEquals($data, $result);
    }

    public function testSupportsArrayDeserialization(): void
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

    public function testDeserializeArray(): void
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

    public function testNormalizerAware(): void
    {
        $normalizerAware = $this->getMockBuilder(NormalizerAwareInterface::class)->getMock();
        $normalizerAware->expects($this->once())
            ->method('setNormalizer')
            ->with($this->isInstanceOf(NormalizerInterface::class));

        new Serializer(array($normalizerAware));
    }

    public function testDenormalizerAware(): void
    {
        $denormalizerAware = $this->getMockBuilder(DenormalizerAwareInterface::class)->getMock();
        $denormalizerAware->expects($this->once())
            ->method('setDenormalizer')
            ->with($this->isInstanceOf(DenormalizerInterface::class));

        new Serializer(array($denormalizerAware));
    }

    public function testDeserializeObjectConstructorWithObjectTypeHint(): void
    {
        $jsonData = '{"bar":{"value":"baz"}}';

        $serializer = new Serializer(array(new ObjectNormalizer()), array('json' => new JsonEncoder()));

        $this->assertEquals(new Foo(new Bar('baz')), $serializer->deserialize($jsonData, Foo::class, 'json'));
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

    public function setTitle($title): void
    {
        $this->title = $title;
    }

    public function getNumbers()
    {
        return $this->numbers;
    }

    public function setNumbers($numbers): void
    {
        $this->numbers = $numbers;
    }

    public function toArray()
    {
        return array('title' => $this->title, 'numbers' => $this->numbers);
    }
}

class Foo
{
    private $bar;

    public function __construct(Bar $bar)
    {
        $this->bar = $bar;
    }
}

class Bar
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}
