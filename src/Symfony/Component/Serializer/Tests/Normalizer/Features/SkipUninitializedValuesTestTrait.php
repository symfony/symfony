<?php

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Test AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES.
 */
trait SkipUninitializedValuesTestTrait
{
    abstract protected function getNormalizerForSkipUninitializedValues(): NormalizerInterface;

    public function testSkipUninitializedValues()
    {
        $object = new TypedPropertiesObject();

        $normalizer = $this->getNormalizerForSkipUninitializedValues();
        $result = $normalizer->normalize($object, null, ['skip_uninitialized_values' => true, 'groups' => ['foo']]);
        $this->assertSame(['initialized' => 'value'], $result);
    }

    public function testWithoutSkipUninitializedValues()
    {
        $object = new TypedPropertiesObject();

        $normalizer = $this->getNormalizerForSkipUninitializedValues();
        $this->expectException(UninitializedPropertyException::class);
        $normalizer->normalize($object, null, ['groups' => ['foo']]);
    }
}
