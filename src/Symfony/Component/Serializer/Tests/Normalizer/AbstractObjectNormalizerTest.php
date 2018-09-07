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
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

class AbstractObjectNormalizerTest extends TestCase
{
    public function testDenormalize()
    {
        $normalizer = new AbstractObjectNormalizerDummy();
        $normalizedData = $normalizer->denormalize(array('foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz'), __NAMESPACE__.'\Dummy');

        $this->assertSame('foo', $normalizedData->foo);
        $this->assertNull($normalizedData->bar);
        $this->assertSame('baz', $normalizedData->baz);
    }

    public function testInstantiateObjectDenormalizer()
    {
        $data = array('foo' => 'foo', 'bar' => 'bar', 'baz' => 'baz');
        $class = __NAMESPACE__.'\Dummy';
        $context = array();

        $normalizer = new AbstractObjectNormalizerDummy();

        $this->assertInstanceOf(__NAMESPACE__.'\Dummy', $normalizer->instantiateObject($data, $class, $context, new \ReflectionClass($class), array()));
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\ExtraAttributesException
     * @expectedExceptionMessage Extra attributes are not allowed ("fooFoo", "fooBar" are unknown).
     */
    public function testDenormalizeWithExtraAttributes()
    {
        $factory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $normalizer = new AbstractObjectNormalizerDummy($factory);
        $normalizer->denormalize(
            array('fooFoo' => 'foo', 'fooBar' => 'bar'),
            __NAMESPACE__.'\Dummy',
            'any',
            array('allow_extra_attributes' => false)
        );
    }

    /**
     * @expectedException \Symfony\Component\Serializer\Exception\ExtraAttributesException
     * @expectedExceptionMessage Extra attributes are not allowed ("fooFoo", "fooBar" are unknown).
     */
    public function testDenormalizeWithExtraAttributesAndNoGroupsWithMetadataFactory()
    {
        $normalizer = new AbstractObjectNormalizerWithMetadata();
        $normalizer->denormalize(
            array('fooFoo' => 'foo', 'fooBar' => 'bar', 'bar' => 'bar'),
            Dummy::class,
            'any',
            array('allow_extra_attributes' => false)
        );
    }

    public function testDenormalizeCollectionDecodedFromXmlWithOneChild()
    {
        $denormalizer = $this->getDenormalizerForDummyCollection();

        $dummyCollection = $denormalizer->denormalize(
            array(
                'children' => array(
                    'bar' => 'first',
                ),
            ),
            DummyCollection::class,
            'xml'
        );

        $this->assertInstanceOf(DummyCollection::class, $dummyCollection);
        $this->assertInternalType('array', $dummyCollection->children);
        $this->assertCount(1, $dummyCollection->children);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[0]);
    }

    public function testDenormalizeCollectionDecodedFromXmlWithTwoChildren()
    {
        $denormalizer = $this->getDenormalizerForDummyCollection();

        $dummyCollection = $denormalizer->denormalize(
            array(
                'children' => array(
                    array('bar' => 'first'),
                    array('bar' => 'second'),
                ),
            ),
            DummyCollection::class,
            'xml'
        );

        $this->assertInstanceOf(DummyCollection::class, $dummyCollection);
        $this->assertInternalType('array', $dummyCollection->children);
        $this->assertCount(2, $dummyCollection->children);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[0]);
        $this->assertInstanceOf(DummyChild::class, $dummyCollection->children[1]);
    }

    private function getDenormalizerForDummyCollection()
    {
        $extractor = $this->getMockBuilder(PhpDocExtractor::class)->getMock();
        $extractor->method('getTypes')
            ->will($this->onConsecutiveCalls(
                array(
                    new Type(
                        'array',
                        false,
                        null,
                        true,
                        new Type('int'),
                        new Type('object', false, DummyChild::class)
                    ),
                ),
                null
            ));

        $denormalizer = new AbstractObjectNormalizerCollectionDummy(null, null, $extractor);
        $arrayDenormalizer = new ArrayDenormalizerDummy();
        $serializer = new SerializerCollectionDummy(array($arrayDenormalizer, $denormalizer));
        $arrayDenormalizer->setSerializer($serializer);
        $denormalizer->setSerializer($serializer);

        return $denormalizer;
    }

    /**
     * Test that additional attributes throw an exception if no metadata factory is specified.
     *
     * @expectedException \Symfony\Component\Serializer\Exception\LogicException
     * @expectedExceptionMessage A class metadata factory must be provided in the constructor when setting "allow_extra_attributes" to false.
     */
    public function testExtraAttributesException()
    {
        $normalizer = new ObjectNormalizer();

        $normalizer->denormalize(array(), \stdClass::class, 'xml', array(
            'allow_extra_attributes' => false,
        ));
    }
}

class AbstractObjectNormalizerDummy extends AbstractObjectNormalizer
{
    protected function extractAttributes($object, $format = null, array $context = array())
    {
    }

    protected function getAttributeValue($object, $attribute, $format = null, array $context = array())
    {
    }

    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = array())
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = array())
    {
        return \in_array($attribute, array('foo', 'baz'));
    }

    public function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }
}

class Dummy
{
    public $foo;
    public $bar;
    public $baz;
}

class AbstractObjectNormalizerWithMetadata extends AbstractObjectNormalizer
{
    public function __construct()
    {
        parent::__construct(new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader())));
    }

    protected function extractAttributes($object, $format = null, array $context = array())
    {
    }

    protected function getAttributeValue($object, $attribute, $format = null, array $context = array())
    {
    }

    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = array())
    {
        $object->$attribute = $value;
    }
}

class DummyCollection
{
    /** @var DummyChild[] */
    public $children;
}

class DummyChild
{
    public $bar;
}

class SerializerCollectionDummy implements SerializerInterface, DenormalizerInterface
{
    private $normalizers;

    /**
     * @param DenormalizerInterface[] $normalizers
     */
    public function __construct($normalizers)
    {
        $this->normalizers = $normalizers;
    }

    public function serialize($data, $format, array $context = array())
    {
    }

    public function deserialize($data, $type, $format, array $context = array())
    {
    }

    public function denormalize($data, $type, $format = null, array $context = array())
    {
        foreach ($this->normalizers as $normalizer) {
            if ($normalizer instanceof DenormalizerInterface && $normalizer->supportsDenormalization($data, $type, $format, $context)) {
                return $normalizer->denormalize($data, $type, $format, $context);
            }
        }
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return true;
    }
}

class AbstractObjectNormalizerCollectionDummy extends AbstractObjectNormalizer
{
    protected function extractAttributes($object, $format = null, array $context = array())
    {
    }

    protected function getAttributeValue($object, $attribute, $format = null, array $context = array())
    {
    }

    protected function setAttributeValue($object, $attribute, $value, $format = null, array $context = array())
    {
        $object->$attribute = $value;
    }

    protected function isAllowedAttribute($classOrObject, $attribute, $format = null, array $context = array())
    {
        return true;
    }

    public function instantiateObject(array &$data, $class, array &$context, \ReflectionClass $reflectionClass, $allowedAttributes, string $format = null)
    {
        return parent::instantiateObject($data, $class, $context, $reflectionClass, $allowedAttributes, $format);
    }

    public function serialize($data, $format, array $context = array())
    {
    }

    public function deserialize($data, $type, $format, array $context = array())
    {
    }
}

class ArrayDenormalizerDummy implements DenormalizerInterface, SerializerAwareInterface
{
    /**
     * @var SerializerInterface|DenormalizerInterface
     */
    private $serializer;

    /**
     * {@inheritdoc}
     *
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $serializer = $this->serializer;
        $class = substr($class, 0, -2);

        foreach ($data as $key => $value) {
            $data[$key] = $serializer->denormalize($value, $class, $format, $context);
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null, array $context = array())
    {
        return '[]' === substr($type, -2)
            && $this->serializer->supportsDenormalization($data, substr($type, 0, -2), $format, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }
}
