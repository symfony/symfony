<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\JsonSerializableDummy;

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
        $this->createNormalizer();
    }

    private function createNormalizer(array $defaultContext = [])
    {
        $this->serializer = $this->getMockBuilder(JsonSerializerNormalizer::class)->getMock();
        $this->normalizer = new JsonSerializableNormalizer(null, null, $defaultContext);
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
            ->willReturnCallback(function ($data) {
                $this->assertArraySubset(['foo' => 'a', 'bar' => 'b', 'baz' => 'c'], $data);

                return 'string_object';
            })
        ;

        $this->assertEquals('string_object', $this->normalizer->normalize(new JsonSerializableDummy()));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\CircularReferenceException
     */
    public function testCircularNormalize()
    {
        $this->doTestCircularNormalize();
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\CircularReferenceException
     */
    public function testLegacyCircularNormalize()
    {
        $this->doTestCircularNormalize(true);
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\CircularReferenceException
     */
    private function doTestCircularNormalize(bool $legacy = false)
    {
        $legacy ? $this->normalizer->setCircularReferenceLimit(1) : $this->createNormalizer([JsonSerializableNormalizer::CIRCULAR_REFERENCE_LIMIT => 1]);

        $this->serializer
            ->expects($this->once())
            ->method('normalize')
            ->willReturnCallback(function ($data, $format, $context) {
                $this->normalizer->normalize($data['qux'], $format, $context);

                return 'string_object';
            })
        ;

        $this->assertEquals('string_object', $this->normalizer->normalize(new JsonSerializableDummy()));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\InvalidArgumentException
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
