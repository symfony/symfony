<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Test AbstractObjectNormalizer::SKIP_UNINITIALIZED_PROPERTIES.
 */
trait SkipUninitializedPropertiesTestTrait
{
    abstract protected function getNormalizerForSkipUninitializedProperties(): NormalizerInterface;

    public function testSkipUninitializedProperties()
    {
        $dummy = new ObjectDummy2();
        $dummy->bar = 'present';

        $normalizer = $this->getNormalizerForSkipUninitializedProperties();
        $result = $normalizer->normalize($dummy, null, ['skip_uninitialized_properties' => true]);
        $this->assertSame(['bar' => 'present'], $result);
    }

    public function testWithoutSkipUninitializedProperties()
    {
        $this->expectException(UninitializedPropertyException::class);

        $normalizer = $this->getNormalizerForSkipUninitializedProperties();
        $normalizer->normalize(new ObjectDummy2(), null);
    }
}
