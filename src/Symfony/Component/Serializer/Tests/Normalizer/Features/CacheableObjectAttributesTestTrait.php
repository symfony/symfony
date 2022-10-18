<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Normalizer\Features;

use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * This test ensures that attributes caching implemented in AbstractObjectNormalizer
 * does not break normalization of multiple objects having different set of initialized/unInitialized properties.
 *
 * The attributes cache MUST NOT depend on a specific object state, so that cached attributes could be reused
 * while normalizing any number of instances of the same class in any order.
 */
trait CacheableObjectAttributesTestTrait
{
    /**
     * Returns a collection of objects to be normalized and compared with the expected array.
     * It is a specific object normalizer test class responsibility to prepare testing data.
     */
    abstract protected function getObjectCollectionWithExpectedArray(): array;

    abstract protected function getNormalizerForCacheableObjectAttributesTest(): AbstractObjectNormalizer;

    /**
     * The same normalizer instance normalizes two objects of the same class in a row:
     *  1. an object having some uninitialized properties
     *  2. an object with all properties being initialized.
     */
    public function testObjectCollectionNormalization()
    {
        [$collection, $expectedArray] = $this->getObjectCollectionWithExpectedArray();
        $this->assertCollectionNormalizedProperly($collection, $expectedArray);
    }

    /**
     * The same normalizer instance normalizes two objects of the same class in a row:
     *  1. an object with all properties being initialized
     *  2. an object having some uninitialized properties.
     */
    public function testReversedObjectCollectionNormalization()
    {
        [$collection, $expectedArray] = array_map('array_reverse', $this->getObjectCollectionWithExpectedArray());
        $this->assertCollectionNormalizedProperly($collection, $expectedArray);
    }

    private function assertCollectionNormalizedProperly(array $collection, array $expectedArray): void
    {
        self::assertCount(\count($expectedArray), $collection);
        $normalizer = $this->getNormalizerForCacheableObjectAttributesTest();
        foreach ($collection as $i => $object) {
            $result = $normalizer->normalize($object);
            self::assertSame($expectedArray[$i], $result);
        }
    }
}
