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

use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Serializer\Mapping\Loader\BetterAnnotationLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\MetadataAwareNormalizer;
use Symfony\Component\Serializer\PropertyManager\MetadataAwarePropertyTypeExtractor;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\AccessorDummy;
use Symfony\Component\Serializer\Tests\Fixtures\CompositionChildDummy;
use Symfony\Component\Serializer\Tests\Fixtures\CompositionDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ExcludeDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ExclusionPolicyAllDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ExclusionPolicyDefaultDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ExclusionPolicyNoneDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ExposeDummy;
use Symfony\Component\Serializer\Tests\Fixtures\GroupsDummy;
use Symfony\Component\Serializer\Tests\Fixtures\InheritanceDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ReadOnlyClassDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ReadOnlyDummy;
use Symfony\Component\Serializer\Tests\Fixtures\SerializedNameDummy;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MetadataAwareNormalizerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var MetadataAwareNormalizer
     */
    private $normalizer;
    /**
     * @var SerializerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $serializer;

    protected function setUp()
    {
        $this->serializer = $this->getMock(__NAMESPACE__.'\MetadataObjectSerializerNormalizer');
        $classMetadataFactory = new ClassMetadataFactory(new BetterAnnotationLoader(new AnnotationReader()));
        $this->normalizer = new MetadataAwareNormalizer($classMetadataFactory, null, null, null, new MetadataAwarePropertyTypeExtractor($classMetadataFactory));
        $this->normalizer->setSerializer($this->serializer);
    }

    public function testAccessorNormalize()
    {
        $data = $this->normalizer->normalize(new AccessorDummy());

        $this->assertTrue(isset($data['model']));
        $this->assertEquals('getModel', $data['model']);
    }

    public function testAccessorDenormalize()
    {
        $data = ['model' => 'model_val'];
        $obj = $this->normalizer->denormalize($data, AccessorDummy::class);

        $this->assertEquals('model_val_setter', $obj->model);
    }

    public function testCompositionNormalize()
    {
        $serializer = new MicroSerializer($this->normalizer);
        $this->normalizer->setSerializer($serializer);

        $data = $this->normalizer->normalize(new CompositionDummy(true));

        $this->assertTrue(isset($data['child']));
        $this->assertTrue(isset($data['child']['color']));
        $this->assertEquals('val_color', $data['child']['color']);
        $this->assertTrue(isset($data['name']));
    }

    public function testCompositionDenormalize()
    {
        $serializer = new MicroSerializer($this->normalizer);
        $this->normalizer->setSerializer($serializer);

        $data = json_decode('{"name":"Foobar","child":{"super_model":"val_model","car_size":"val_size","color":"val_color"}}', true);
        $obj = $this->normalizer->denormalize($data, CompositionDummy::class);

        $this->assertEquals('Foobar', $obj->name);
        $child = $obj->getChild();
        $this->assertInstanceOf(CompositionChildDummy::class, $child);
        $this->assertEquals('val_color', $child->color);
    }

    public function testInheritanceNormalize()
    {
        $data = $this->normalizer->normalize(new InheritanceDummy(true));

        $this->assertTrue(isset($data['name']));
        $this->assertTrue(isset($data['age']));
    }

    public function testInheritanceDenormalize()
    {
        $data = ['name' => 'Foo', 'age' => 'Bar'];
        $obj = $this->normalizer->denormalize($data, InheritanceDummy::class);

        $this->assertEquals('Foo', $obj->name);
        $this->assertEquals('Bar', $obj->age);
    }

    public function testExcludeNormalize()
    {
        $data = $this->normalizer->normalize(new ExcludeDummy(true));

        $this->assertFalse(isset($data['model']));
        $this->assertTrue(isset($data['size']));
    }

    public function testExcludeDenormalize()
    {
        $data = ['model' => 'model_value', 'size' => 'size_value'];
        $obj = $this->normalizer->denormalize($data, ExcludeDummy::class);

        $this->assertEquals(null, $obj->model);
        $this->assertEquals('size_value', $obj->size);
    }

    public function testExclusionPolicyNormalizeDefault()
    {
        $data = $this->normalizer->normalize(new ExclusionPolicyDefaultDummy(true));

        $this->assertFalse(isset($data['model']));
        $this->assertTrue(isset($data['size']));
        $this->assertTrue(isset($data['color']));
    }

    public function testExclusionPolicyNormalizeNone()
    {
        $data = $this->normalizer->normalize(new ExclusionPolicyNoneDummy(true));

        $this->assertFalse(isset($data['model']));
        $this->assertTrue(isset($data['size']));
        $this->assertTrue(isset($data['color']));
    }

    public function testExclusionPolicyNormalizeAll()
    {
        $data = $this->normalizer->normalize(new ExclusionPolicyAllDummy(true));

        $this->assertFalse(isset($data['model']));
        $this->assertTrue(isset($data['size']));
        $this->assertFalse(isset($data['color']));
    }

    public function testExclusionPolicyDenormalizeDefault()
    {
        $data = ['model' => 'model_value', 'size' => 'size_value', 'color' => 'color_value'];
        $obj = $this->normalizer->denormalize($data, ExclusionPolicyDefaultDummy::class);

        $this->assertEquals(null, $obj->model);
        $this->assertEquals('size_value', $obj->size);
        $this->assertEquals('color_value', $obj->color);
    }

    public function testExclusionPolicyDenormalizeNone()
    {
        $data = ['model' => 'model_value', 'size' => 'size_value', 'color' => 'color_value'];
        $obj = $this->normalizer->denormalize($data, ExclusionPolicyNoneDummy::class);

        $this->assertEquals(null, $obj->model);
        $this->assertEquals('size_value', $obj->size);
        $this->assertEquals('color_value', $obj->color);
    }

    public function testExclusionPolicyDenormalizeAll()
    {
        $data = ['model' => 'model_value', 'size' => 'size_value', 'color' => 'color_value'];
        $obj = $this->normalizer->denormalize($data, ExclusionPolicyAllDummy::class);

        $this->assertEquals(null, $obj->model);
        $this->assertEquals('size_value', $obj->size);
        $this->assertEquals(null, $obj->color);
    }

    public function testExposeNormalize()
    {
        $data = $this->normalizer->normalize(new ExposeDummy(true));

        $this->assertTrue(isset($data['model']));
        $this->assertFalse(isset($data['size']));
    }

    public function testExposeDenormalize()
    {
        $data = ['model' => 'model_value', 'size' => 'size_value'];
        $obj = $this->normalizer->denormalize($data, ExposeDummy::class);

        $this->assertEquals('model_value', $obj->model);
        $this->assertEquals(null, $obj->size);
    }

    public function testGroupsNormalize()
    {
        $data = $this->normalizer->normalize(new GroupsDummy(true), null, ['groups' => ['First']]);
        $this->assertTrue(isset($data['model']));
        $this->assertTrue(isset($data['size']));
        $this->assertFalse(isset($data['color']));

        $data = $this->normalizer->normalize(new GroupsDummy(true), null, ['groups' => ['Second']]);
        $this->assertTrue(isset($data['model']));
        $this->assertFalse(isset($data['size']));
        $this->assertFalse(isset($data['color']));

        $data = $this->normalizer->normalize(new GroupsDummy(true), null, ['groups' => ['First', 'Second']]);
        $this->assertTrue(isset($data['model']));
        $this->assertTrue(isset($data['size']));
        $this->assertFalse(isset($data['color']));

        $data = $this->normalizer->normalize(new GroupsDummy(true), null, ['groups' => []]);
        $this->assertFalse(isset($data['model']));
        $this->assertFalse(isset($data['size']));
        $this->assertFalse(isset($data['color']));
    }

    public function testGroupsDenormalize()
    {
        $data = ['model' => 'model_value', 'size' => 'size_value', 'color' => 'color_value'];

        $obj = $this->normalizer->denormalize($data, GroupsDummy::class, null, ['groups' => ['First']]);
        $this->assertEquals('model_value', $obj->model);
        $this->assertEquals('size_value', $obj->size);
        $this->assertEquals(null, $obj->color);

        $obj = $this->normalizer->denormalize($data, GroupsDummy::class, null, ['groups' => ['Second']]);
        $this->assertEquals('model_value', $obj->model);
        $this->assertEquals(null, $obj->size);
        $this->assertEquals(null, $obj->color);

        $obj = $this->normalizer->denormalize($data, GroupsDummy::class, null, ['groups' => ['First', 'Second']]);
        $this->assertEquals('model_value', $obj->model);
        $this->assertEquals('size_value', $obj->size);
        $this->assertEquals(null, $obj->color);

        $obj = $this->normalizer->denormalize($data, GroupsDummy::class, null, ['groups' => []]);
        $this->assertEquals(null, $obj->model);
        $this->assertEquals(null, $obj->size);
        $this->assertEquals(null, $obj->color);
    }

    public function testReadOnlyNormalize()
    {
        $data = $this->normalizer->normalize(new ReadOnlyDummy(true));

        $this->assertTrue(isset($data['model']));
        $this->assertTrue(isset($data['size']));
    }

    public function testReadOnlyDenormalize()
    {
        $data = ['model' => 'model_value', 'size' => 'size_value'];
        $obj = $this->normalizer->denormalize($data, ReadOnlyDummy::class);

        $this->assertEquals(null, $obj->model);
        $this->assertEquals('size_value', $obj->size);
    }

    public function testReadOnlyNormalizeClass()
    {
        $data = $this->normalizer->normalize(new ReadOnlyClassDummy(true));

        $this->assertTrue(isset($data['model']));
        $this->assertTrue(isset($data['size']));
        $this->assertTrue(isset($data['color']));
    }

    public function testReadOnlyDenormalizeClass()
    {
        $data = ['model' => 'model_value', 'size' => 'size_value', 'color' => 'color_value'];
        $obj = $this->normalizer->denormalize($data, ReadOnlyClassDummy::class);

        $this->assertEquals('model_value', $obj->model);
        $this->assertEquals(null, $obj->size);
        $this->assertEquals(null, $obj->color);
    }

    public function testSerializedNameNormalize()
    {
        $data = $this->normalizer->normalize(new SerializedNameDummy(true));

        $this->assertTrue(isset($data['super_model']));
        $this->assertTrue(isset($data['carSize']));
        $this->assertTrue(isset($data['color']));
    }

    public function testSerializedNameDenormalize()
    {
        $serializer = $this->getMock(__NAMESPACE__.'\MetadataObjectSerializerNormalizer');
        $classMetadataFactory = new ClassMetadataFactory(new BetterAnnotationLoader(new AnnotationReader()));
        $normalizer = new MetadataAwareNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter(), null, null, new MetadataAwarePropertyTypeExtractor($classMetadataFactory));
        $normalizer->setSerializer($serializer);

        $data = ['super_model' => 'model_val', 'car_size' => 'size_val', 'color' => 'color_val'];
        $obj = $normalizer->denormalize($data, SerializedNameDummy::class);

        $this->assertEquals('model_val', $obj->model);
        $this->assertEquals('size_val', $obj->carSize);
        $this->assertEquals('color_val', $obj->color);
    }

    /**
     * Test when the json data is named as the properties. They should be ignored.
     */
    public function testSerializedNameDenormalizeWhenIgnoringSerializedName()
    {
        $serializer = $this->getMock(__NAMESPACE__.'\MetadataObjectSerializerNormalizer');
        $classMetadataFactory = new ClassMetadataFactory(new BetterAnnotationLoader(new AnnotationReader()));
        $normalizer = new MetadataAwareNormalizer($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter(), null, null, new MetadataAwarePropertyTypeExtractor($classMetadataFactory));
        $normalizer->setSerializer($serializer);

        $data = ['model' => 'model_val', 'carSize' => 'size_val', 'color' => 'color_val'];
        $obj = $normalizer->denormalize($data, SerializedNameDummy::class);

        $this->assertEquals(null, $obj->model);
        $this->assertEquals(null, $obj->carSize);
        $this->assertEquals('color_val', $obj->color);
    }
}

abstract class MetadataObjectSerializerNormalizer implements SerializerInterface, NormalizerInterface
{
}

class MicroSerializer implements SerializerInterface, NormalizerInterface, DenormalizerInterface
{
    private $normalizer;

    public function __construct(NormalizerInterface $normalizer)
    {
        $this->normalizer = $normalizer;
    }

    public function normalize($object, $format = null, array $context = array())
    {
        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null)
    {
        return $this->normalizer->normalize($data, $format);
    }

    public function denormalize($data, $class, $format = null, array $context = array())
    {
        if ($this->normalizer instanceof DenormalizerInterface) {
            return $this->normalizer->denormalize($data, $class, $format, $context);
        }

        throw new \RuntimeException('Test function not implemented');
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        if ($this->normalizer instanceof DenormalizerInterface) {
            return $this->normalizer->supportsDenormalization($data, $type, $format);
        }

        throw new \RuntimeException('Test function not implemented');
    }

    public function serialize($data, $format, array $context = array())
    {
        throw new \RuntimeException('Test function not implemented');
    }

    public function deserialize($data, $type, $format, array $context = array())
    {
        throw new \RuntimeException('Test function not implemented');
    }
}
