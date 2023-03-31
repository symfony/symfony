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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderChain;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\AbstractNormalizerDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\IgnoreDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Dummy;
use Symfony\Component\Serializer\Tests\Fixtures\NullableConstructorArgumentDummy;
use Symfony\Component\Serializer\Tests\Fixtures\NullableOptionalConstructorArgumentDummy;
use Symfony\Component\Serializer\Tests\Fixtures\StaticConstructorDummy;
use Symfony\Component\Serializer\Tests\Fixtures\StaticConstructorNormalizer;
use Symfony\Component\Serializer\Tests\Fixtures\VariadicConstructorTypedArgsDummy;

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
     * @var MockObject&ClassMetadataFactoryInterface
     */
    private $classMetadata;

    protected function setUp(): void
    {
        $loader = $this->getMockBuilder(LoaderChain::class)->setConstructorArgs([[]])->getMock();
        $this->classMetadata = $this->getMockBuilder(ClassMetadataFactory::class)->setConstructorArgs([$loader])->getMock();
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

        $result = $this->normalizer->getAllowedAttributes('c', [AbstractNormalizer::GROUPS => ['*']], true);
        $this->assertEquals(['a1', 'a2', 'a3', 'a4'], $result);
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

        $result = $this->normalizer->getAllowedAttributes('c', [AbstractNormalizer::GROUPS => 'test'], false);
        $this->assertEquals([$a2, $a4], $result);

        $result = $this->normalizer->getAllowedAttributes('c', [AbstractNormalizer::GROUPS => ['other']], false);
        $this->assertEquals([$a3, $a4], $result);

        $result = $this->normalizer->getAllowedAttributes('c', [AbstractNormalizer::GROUPS => ['*']], false);
        $this->assertEquals([$a1, $a2, $a3, $a4], $result);
    }

    public function testObjectWithStaticConstructor()
    {
        $normalizer = new StaticConstructorNormalizer();
        $dummy = $normalizer->denormalize(['foo' => 'baz'], StaticConstructorDummy::class);

        $this->assertInstanceOf(StaticConstructorDummy::class, $dummy);
        $this->assertEquals('baz', $dummy->quz);
        $this->assertNull($dummy->foo);
    }

    public function testObjectWithNullableConstructorArgument()
    {
        $normalizer = new ObjectNormalizer();
        $dummy = $normalizer->denormalize(['foo' => null], NullableOptionalConstructorArgumentDummy::class);

        $this->assertNull($dummy->getFoo());
    }

    public function testObjectWithNullableConstructorArgumentWithoutInput()
    {
        $normalizer = new ObjectNormalizer();
        $dummy = $normalizer->denormalize([], NullableOptionalConstructorArgumentDummy::class);

        $this->assertNull($dummy->getFoo());
    }

    public function testObjectWithNullableNonOptionalConstructorArgument()
    {
        $normalizer = new ObjectNormalizer();
        $dummy = $normalizer->denormalize(['foo' => null], NullableConstructorArgumentDummy::class);

        $this->assertNull($dummy->getFoo());
    }

    public function testObjectWithNullableNonOptionalConstructorArgumentWithoutInput()
    {
        $normalizer = new ObjectNormalizer();
        $dummy = $normalizer->denormalize([], NullableConstructorArgumentDummy::class);

        $this->assertNull($dummy->getFoo());
    }

    /**
     * @dataProvider getNormalizer
     */
    public function testObjectWithVariadicConstructorTypedArguments(AbstractNormalizer $normalizer)
    {
        $d1 = new Dummy();
        $d1->foo = 'Foo';
        $d1->bar = 'Bar';
        $d1->baz = 'Baz';
        $d1->qux = 'Quz';
        $d2 = new Dummy();
        $d2->foo = 'FOO';
        $d2->bar = 'BAR';
        $d2->baz = 'BAZ';
        $d2->qux = 'QUZ';
        $obj = new VariadicConstructorTypedArgsDummy($d1, $d2);

        $serializer = new Serializer([$normalizer], [new JsonEncoder()]);
        $normalizer->setSerializer($serializer);
        $data = $serializer->serialize($obj, 'json');
        $dummy = $normalizer->denormalize(json_decode($data, true), VariadicConstructorTypedArgsDummy::class);
        $this->assertInstanceOf(VariadicConstructorTypedArgsDummy::class, $dummy);
        $this->assertCount(2, $dummy->getFoo());
        foreach ($dummy->getFoo() as $foo) {
            $this->assertInstanceOf(Dummy::class, $foo);
        }

        $dummy = $serializer->deserialize($data, VariadicConstructorTypedArgsDummy::class, 'json');
        $this->assertInstanceOf(VariadicConstructorTypedArgsDummy::class, $dummy);
        $this->assertCount(2, $dummy->getFoo());
        foreach ($dummy->getFoo() as $foo) {
            $this->assertInstanceOf(Dummy::class, $foo);
        }
    }

    /**
     * @dataProvider getNormalizer
     */
    public function testVariadicSerializationWithPreservingKeys(AbstractNormalizer $normalizer)
    {
        $d1 = new Dummy();
        $d1->foo = 'Foo';
        $d1->bar = 'Bar';
        $d1->baz = 'Baz';
        $d1->qux = 'Quz';
        $d2 = new Dummy();
        $d2->foo = 'FOO';
        $d2->bar = 'BAR';
        $d2->baz = 'BAZ';
        $d2->qux = 'QUZ';
        $arr = ['d1' => $d1, 'd2' => $d2];
        $obj = new VariadicConstructorTypedArgsDummy(...$arr);

        $serializer = new Serializer([$normalizer], [new JsonEncoder()]);
        $normalizer->setSerializer($serializer);
        $this->assertEquals(
            '{"foo":{"d1":{"foo":"Foo","bar":"Bar","baz":"Baz","qux":"Quz"},"d2":{"foo":"FOO","bar":"BAR","baz":"BAZ","qux":"QUZ"}}}',
            $data = $serializer->serialize($obj, 'json')
        );

        $dummy = $normalizer->denormalize(json_decode($data, true), VariadicConstructorTypedArgsDummy::class);
        $this->assertInstanceOf(VariadicConstructorTypedArgsDummy::class, $dummy);
        $this->assertEquals($arr, $dummy->getFoo());
    }

    public static function getNormalizer()
    {
        $extractor = new PhpDocExtractor();

        yield [new PropertyNormalizer()];
        yield [new PropertyNormalizer(null, null, $extractor)];
        yield [new ObjectNormalizer()];
        yield [new ObjectNormalizer(null, null, null, $extractor)];
    }

    public function testIgnore()
    {
        $classMetadata = new ClassMetadata(IgnoreDummy::class);
        $attributeMetadata = new AttributeMetadata('ignored1');
        $attributeMetadata->setIgnore(true);
        $classMetadata->addAttributeMetadata($attributeMetadata);
        $this->classMetadata->method('getMetadataFor')->willReturn($classMetadata);

        $dummy = new IgnoreDummy();
        $dummy->ignored1 = 'hello';

        $normalizer = new PropertyNormalizer($this->classMetadata);

        $this->assertSame([], $normalizer->normalize($dummy));
    }
}
