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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\JsonSerializableNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\JsonSerializableCircularReferenceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\JsonSerializableDummy;
use Symfony\Component\Serializer\Tests\Normalizer\Features\CircularReferenceTestTrait;

/**
 * @author Fred Cox <mcfedr@gmail.com>
 */
class JsonSerializableNormalizerTest extends TestCase
{
    use CircularReferenceTestTrait;

    /**
     * @var JsonSerializableNormalizer
     */
    private $normalizer;

    /**
     * @var MockObject&JsonSerializerNormalizer
     */
    private $serializer;

    protected function setUp(): void
    {
        $this->createNormalizer();
    }

    private function createNormalizer(array $defaultContext = [])
    {
        $this->serializer = $this->createMock(JsonSerializerNormalizer::class);
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
                $this->assertSame(['foo' => 'a', 'bar' => 'b', 'baz' => 'c'], array_diff_key($data, ['qux' => '']));

                return 'string_object';
            })
        ;

        $this->assertEquals('string_object', $this->normalizer->normalize(new JsonSerializableDummy()));
    }

    public function testCircularNormalize()
    {
        $this->expectException(CircularReferenceException::class);
        $this->createNormalizer([JsonSerializableNormalizer::CIRCULAR_REFERENCE_LIMIT => 1]);

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

    protected function getNormalizerForCircularReference(array $defaultContext): JsonSerializableNormalizer
    {
        $normalizer = new JsonSerializableNormalizer(null, null, $defaultContext);
        new Serializer([$normalizer]);

        return $normalizer;
    }

    protected function getSelfReferencingModel()
    {
        return new JsonSerializableCircularReferenceDummy();
    }

    public function testInvalidDataThrowException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The object must implement "JsonSerializable".');
        $this->normalizer->normalize(new \stdClass());
    }
}

abstract class JsonSerializerNormalizer implements SerializerInterface, NormalizerInterface
{
}
