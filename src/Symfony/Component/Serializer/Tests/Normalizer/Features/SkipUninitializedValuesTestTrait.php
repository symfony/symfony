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

use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * Test AbstractObjectNormalizer::SKIP_UNINITIALIZED_VALUES.
 */
trait SkipUninitializedValuesTestTrait
{
    abstract protected function getNormalizerForSkipUninitializedValues(): AbstractObjectNormalizer;

    /**
     * @dataProvider skipUninitializedValuesFlagProvider
     */
    public function testSkipUninitializedValues(array $context)
    {
        $object = new TypedPropertiesObjectWithGetters();

        $normalizer = $this->getNormalizerForSkipUninitializedValues();
        $result = $normalizer->normalize($object, null, $context);
        $this->assertSame(['initialized' => 'value'], $result);

        $normalizer->denormalize(
            ['unInitialized' => 'value'],
            TypedPropertiesObjectWithGetters::class,
            null,
            ['object_to_populate' => $objectToPopulate = new TypedPropertiesObjectWithGetters(), 'deep_object_to_populate' => true] + $context
        );

        $this->assertSame('value', $objectToPopulate->getUninitialized());
    }

    public static function skipUninitializedValuesFlagProvider(): iterable
    {
        yield 'passed manually' => [['skip_uninitialized_values' => true, 'groups' => ['foo']]];
        yield 'using default context value' => [['groups' => ['foo']]];
    }

    public function testWithoutSkipUninitializedValues()
    {
        $object = new TypedPropertiesObjectWithGetters();

        $normalizer = $this->getNormalizerForSkipUninitializedValues();

        try {
            $normalizer->normalize($object, null, ['skip_uninitialized_values' => false, 'groups' => ['foo']]);
            $this->fail('Normalizing an object with uninitialized property should have failed');
        } catch (UninitializedPropertyException $e) {
            self::assertSame('The property "Symfony\Component\Serializer\Tests\Normalizer\Features\TypedPropertiesObject::$unInitialized" is not readable because it is typed "string". You should initialize it or declare a default value instead.', $e->getMessage());
        } catch (\Error $e) {
            self::assertSame('Typed property Symfony\Component\Serializer\Tests\Normalizer\Features\TypedPropertiesObject::$unInitialized must not be accessed before initialization', $e->getMessage());
        }
    }
}
