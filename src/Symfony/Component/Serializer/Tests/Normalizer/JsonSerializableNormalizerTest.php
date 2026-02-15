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

    public function testSupportNormalization()
    {
        $normalizer = new JsonSerializableNormalizer();
        $normalizer->setSerializer($this->createStub(JsonSerializerNormalizer::class));

        $this->assertTrue($normalizer->supportsNormalization(new JsonSerializableDummy()));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
    }

    public function testNormalize()
    {
        $serializer = $this->createMock(JsonSerializerNormalizer::class);
        $serializer
            ->expects($this->once())
            ->method('normalize')
            ->willReturnCallback(function ($data) {
                $this->assertSame(['foo' => 'a', 'bar' => 'b', 'baz' => 'c'], array_diff_key($data, ['qux' => '']));

                return 'string_object';
            })
        ;
        $normalizer = new JsonSerializableNormalizer();
        $normalizer->setSerializer($serializer);

        $this->assertEquals('string_object', $normalizer->normalize(new JsonSerializableDummy()));
    }

    public function testCircularNormalize()
    {
        $normalizer = new JsonSerializableNormalizer(null, null, [JsonSerializableNormalizer::CIRCULAR_REFERENCE_LIMIT => 1]);

        $this->expectException(CircularReferenceException::class);

        $serializer = $this->createMock(JsonSerializerNormalizer::class);
        $serializer
            ->expects($this->once())
            ->method('normalize')
            ->willReturnCallback(static function ($data, $format, $context) use ($normalizer) {
                $normalizer->normalize($data['qux'], $format, $context);

                return 'string_object';
            })
        ;
        $normalizer->setSerializer($serializer);

        $this->assertEquals('string_object', $normalizer->normalize(new JsonSerializableDummy()));
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
        $normalizer = new JsonSerializableNormalizer();
        $normalizer->setSerializer($this->createStub(JsonSerializerNormalizer::class));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The object must implement "JsonSerializable".');
        $normalizer->normalize(new \stdClass());
    }
}

abstract class JsonSerializerNormalizer implements SerializerInterface, NormalizerInterface
{
}
