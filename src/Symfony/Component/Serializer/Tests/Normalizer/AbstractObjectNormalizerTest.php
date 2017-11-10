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
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

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
        $normalizer = new AbstractObjectNormalizerDummy();
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
        return in_array($attribute, array('foo', 'baz'));
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
