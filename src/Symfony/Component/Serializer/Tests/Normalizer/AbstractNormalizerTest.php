<?php

namespace Symfony\Component\Serializer\Tests\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractNormalizerDummy;
use Symfony\Component\Serializer\Tests\Fixtures\NullableConstructorArgumentDummy;
use Symfony\Component\Serializer\Tests\Fixtures\ProxyDummy;
use Symfony\Component\Serializer\Tests\Fixtures\StaticConstructorDummy;
use Symfony\Component\Serializer\Tests\Fixtures\StaticConstructorNormalizer;

/**
 * Provides a dummy Normalizer which extends the AbstractNormalizer.
 *
 * @author Konstantin S. M. Möllers <ksm.moellers@gmail.com>
 */
class AbstractNormalizerTest extends TestCase
{
    /**
     * @var AbstractNormalizerDummy
     */
    private $normalizer;

    /**
     * @var ClassMetadataFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $classMetadata;

    protected function setUp()
    {
        $loader = $this->getMockBuilder('Symfony\Component\Serializer\Mapping\Loader\LoaderChain')->setConstructorArgs([[]])->getMock();
        $this->classMetadata = $this->getMockBuilder('Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory')->setConstructorArgs([$loader])->getMock();
        $this->normalizer = new AbstractNormalizerDummy($this->classMetadata);
    }

    public function testGetAllowedAttributesAsString()
    {
        $classMetadata = new ClassMetadata('c');

        $a1 = new AttributeMetadata('a1');
        $classMetadata->addAttributeMetadata($a1);

        $a2 = new AttributeMetadata('a2');
        $a2->addGroup('test');
        $classMetadata->addAttributeMetadata($a2);

        $a3 = new AttributeMetadata('a3');
        $a3->addGroup('other');
        $classMetadata->addAttributeMetadata($a3);

        $a4 = new AttributeMetadata('a4');
        $a4->addGroup('test');
        $a4->addGroup('other');
        $classMetadata->addAttributeMetadata($a4);

        $this->classMetadata->method('getMetadataFor')->willReturn($classMetadata);

        $result = $this->normalizer->getAllowedAttributes('c', [AbstractNormalizer::GROUPS => ['test']], true);
        $this->assertEquals(['a2', 'a4'], $result);

        $result = $this->normalizer->getAllowedAttributes('c', [AbstractNormalizer::GROUPS => ['other']], true);
        $this->assertEquals(['a3', 'a4'], $result);
    }

    public function testGetAllowedAttributesAsObjects()
    {
        $classMetadata = new ClassMetadata('c');

        $a1 = new AttributeMetadata('a1');
        $classMetadata->addAttributeMetadata($a1);

        $a2 = new AttributeMetadata('a2');
        $a2->addGroup('test');
        $classMetadata->addAttributeMetadata($a2);

        $a3 = new AttributeMetadata('a3');
        $a3->addGroup('other');
        $classMetadata->addAttributeMetadata($a3);

        $a4 = new AttributeMetadata('a4');
        $a4->addGroup('test');
        $a4->addGroup('other');
        $classMetadata->addAttributeMetadata($a4);

        $this->classMetadata->method('getMetadataFor')->willReturn($classMetadata);

        $result = $this->normalizer->getAllowedAttributes('c', [AbstractNormalizer::GROUPS => ['test']], false);
        $this->assertEquals([$a2, $a4], $result);

        $result = $this->normalizer->getAllowedAttributes('c', [AbstractNormalizer::GROUPS => ['other']], false);
        $this->assertEquals([$a3, $a4], $result);
    }

    public function testObjectToPopulateWithProxy()
    {
        $proxyDummy = new ProxyDummy();

        $context = [AbstractNormalizer::OBJECT_TO_POPULATE => $proxyDummy];

        $normalizer = new ObjectNormalizer();
        $normalizer->denormalize(['foo' => 'bar'], 'Symfony\Component\Serializer\Tests\Fixtures\ToBeProxyfiedDummy', null, $context);

        $this->assertSame('bar', $proxyDummy->getFoo());
    }

    public function testObjectWithStaticConstructor()
    {
        $normalizer = new StaticConstructorNormalizer();
        $dummy = $normalizer->denormalize(['foo' => 'baz'], StaticConstructorDummy::class);

        $this->assertInstanceOf(StaticConstructorDummy::class, $dummy);
        $this->assertEquals('baz', $dummy->quz);
        $this->assertNull($dummy->foo);
    }

    /**
     * @requires PHP 7.1
     */
    public function testObjectWithNullableConstructorArgument()
    {
        $normalizer = new ObjectNormalizer();
        $dummy = $normalizer->denormalize(['foo' => null], NullableConstructorArgumentDummy::class);

        $this->assertNull($dummy->getFoo());
    }
}
