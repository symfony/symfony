<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Exception\CircularReferenceException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Test AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT and AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER.
 */
trait CircularReferenceTestTrait
{
    abstract protected function getNormalizerForCircularReference(): NormalizerInterface;

    abstract protected function getSelfReferencingModel();

    public function testUnableToNormalizeCircularReference()
    {
        $normalizer = $this->getNormalizerForCircularReference();

        $obj = $this->getSelfReferencingModel();

        $this->expectException(CircularReferenceException::class);
        $normalizer->normalize($obj, null, ['circular_reference_limit' => 2]);
    }

    public function testCircularReferenceHandler()
    {
        $normalizer = $this->getNormalizerForCircularReference();

        $obj = $this->getSelfReferencingModel();
        $expected = ['me' => \get_class($obj)];

        $context = [
            'circular_reference_handler' => function ($actualObj, string $format, array $context) use ($obj) {
                $this->assertInstanceOf(\get_class($obj), $actualObj);
                $this->assertSame('test', $format);
                $this->assertArrayHasKey('foo', $context);

                return \get_class($actualObj);
            },
            'foo' => 'bar',
        ];
        $this->assertEquals($expected, $normalizer->normalize($obj, 'test', $context));
    }
}
