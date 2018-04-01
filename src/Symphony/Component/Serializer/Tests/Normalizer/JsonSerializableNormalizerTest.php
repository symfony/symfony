<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symphony\Component\Serializer\Normalizer\NormalizerInterface;
use Symphony\Component\Serializer\SerializerInterface;
use Symphony\Component\Serializer\Tests\Fixtures\JsonSerializableDummy;

/**
 * @author Fred Cox <mcfedr@gmail.com>
 */
class JsonSerializableNormalizerTest extends TestCase
{
    /**
     * @var JsonSerializableNormalizer
     */
    private $normalizer;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|SerializerInterface
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMockBuilder(JsonSerializerNormalizer::class)->getMock();
        $this->normalizer = new JsonSerializableNormalizer();
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testSupportNormalization()
    {
        $this->assertTrue($this->normalizer->supportsNormalization(new JsonSerializableDummy()));
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $this->serializer
            ->expects($this->once())
            ->method('normalize')
            ->will($this->returnCallback(function ($data) {
                $this->assertArraySubset(array('foo' => 'a', 'bar' => 'b', 'baz' => 'c'), $data);

                return 'string_object';
            }))
        ;

        $this->assertEquals('string_object', $this->normalizer->normalize(new JsonSerializableDummy()));
    }

    /**
     * @expectedException \Symphony\Component\Serializer\Exception\CircularReferenceException
     */
    public function testCircularNormalize()
    {
        $this->normalizer->setCircularReferenceLimit(1);

        $this->serializer
            ->expects($this->once())
            ->method('normalize')
            ->will($this->returnCallback(function ($data, $format, $context) {
                $this->normalizer->normalize($data['qux'], $format, $context);

                return 'string_object';
            }))
        ;

        $this->assertEquals('string_object', $this->normalizer->normalize(new JsonSerializableDummy()));
    }

    /**
     * @expectedException \Symphony\Component\Serializer\Exception\InvalidArgumentException
     * @expectedExceptionMessage The object must implement "JsonSerializable".
     */
    public function testInvalidDataThrowException()
    {
        $this->normalizer->normalize(new \stdClass());
    }
}

abstract class JsonSerializerNormalizer implements SerializerInterface, NormalizerInterface
{
}
