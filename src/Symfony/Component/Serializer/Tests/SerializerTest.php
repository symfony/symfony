<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests;

use DateTimeImmutable;
use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\DenormalizationResult;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\DummyFirstChildQuux;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberOne;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberTwo;
use Symfony\Component\Serializer\Tests\Fixtures\NormalizableTraversableDummy;
use Symfony\Component\Serializer\Tests\Fixtures\TraversableDummy;
use Symfony\Component\Serializer\Tests\Normalizer\TestDenormalizer;
use Symfony\Component\Serializer\Tests\Normalizer\TestNormalizer;

class SerializerTest extends TestCase
{
    public function testInterface()
    {
        $serializer = new Serializer();

        $this->assertInstanceOf(\Symfony\Component\Serializer\SerializerInterface::class, $serializer);
        $this->assertInstanceOf(NormalizerInterface::class, $serializer);
        $this->assertInstanceOf(DenormalizerInterface::class, $serializer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\Encoder\EncoderInterface::class, $serializer);
        $this->assertInstanceOf(\Symfony\Component\Serializer\Encoder\DecoderInterface::class, $serializer);
    }

    public function testItThrowsExceptionOnInvalidNormalizer()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The class "stdClass" neither implements "Symfony\\Component\\Serializer\\Normalizer\\NormalizerInterface" nor "Symfony\\Component\\Serializer\\Normalizer\\DenormalizerInterface".');

        new Serializer([new \stdClass()]);
    }

    public function testItThrowsExceptionOnInvalidEncoder()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The class "stdClass" neither implements "Symfony\\Component\\Serializer\\Encoder\\EncoderInterface" nor "Symfony\\Component\\Serializer\\Encoder\\DecoderInterface"');

        new Serializer([], [new \stdClass()]);
    }

    public function testNormalizeNoMatch()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([$this->getMockBuilder(CustomNormalizer::class)->getMock()]);
        $serializer->normalize(new \stdClass(), 'xml');
    }

    public function testNormalizeTraversable()
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $result = $serializer->serialize(new TraversableDummy(), 'json');
        $this->assertEquals('{"foo":"foo","bar":"bar"}', $result);
    }

    public function testNormalizeGivesPriorityToInterfaceOverTraversable()
    {
        $serializer = new Serializer([new CustomNormalizer()], ['json' => new JsonEncoder()]);
        $result = $serializer->serialize(new NormalizableTraversableDummy(), 'json');
        $this->assertEquals('{"foo":"normalizedFoo","bar":"normalizedBar"}', $result);
    }

    public function testNormalizeOnDenormalizer()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([new TestDenormalizer()], []);
        $this->assertTrue($serializer->normalize(new \stdClass(), 'json'));
    }

    public function testDenormalizeNoMatch()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([$this->getMockBuilder(CustomNormalizer::class)->getMock()]);
        $serializer->denormalize('foo', 'stdClass');
    }

    public function testDenormalizeOnNormalizer()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([new TestNormalizer()], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $this->assertTrue($serializer->denormalize(json_encode($data), 'stdClass', 'json'));
    }

    public function testCustomNormalizerCanNormalizeCollectionsAndScalar()
    {
        $serializer = new Serializer([new TestNormalizer()], []);
        $this->assertNull($serializer->normalize(['a', 'b']));
        $this->assertNull($serializer->normalize(new \ArrayObject(['c', 'd'])));
        $this->assertNull($serializer->normalize([]));
        $this->assertNull($serializer->normalize('test'));
    }

    public function testNormalizeWithSupportOnData()
    {
        $normalizer1 = $this->getMockBuilder(NormalizerInterface::class)->getMock();
        $normalizer1->method('supportsNormalization')
            ->willReturnCallback(function ($data, $format) {
                return isset($data->test);
            });
        $normalizer1->method('normalize')->willReturn('test1');

        $normalizer2 = $this->getMockBuilder(NormalizerInterface::class)->getMock();
        $normalizer2->method('supportsNormalization')
            ->willReturn(true);
        $normalizer2->method('normalize')->willReturn('test2');

        $serializer = new Serializer([$normalizer1, $normalizer2]);

        $data = new \stdClass();
        $data->test = true;
        $this->assertEquals('test1', $serializer->normalize($data));

        $this->assertEquals('test2', $serializer->normalize(new \stdClass()));
    }

    public function testDenormalizeWithSupportOnData()
    {
        $denormalizer1 = $this->getMockBuilder(DenormalizerInterface::class)->getMock();
        $denormalizer1->method('supportsDenormalization')
            ->willReturnCallback(function ($data, $type, $format) {
                return isset($data['test1']);
            });
        $denormalizer1->method('denormalize')->willReturn('test1');

        $denormalizer2 = $this->getMockBuilder(DenormalizerInterface::class)->getMock();
        $denormalizer2->method('supportsDenormalization')
            ->willReturn(true);
        $denormalizer2->method('denormalize')->willReturn('test2');

        $serializer = new Serializer([$denormalizer1, $denormalizer2]);

        $this->assertEquals('test1', $serializer->denormalize(['test1' => true], 'test'));

        $this->assertEquals('test2', $serializer->denormalize([], 'test'));
    }

    public function testSerialize()
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $result = $serializer->serialize(Model::fromArray($data), 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testSerializeScalar()
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $result = $serializer->serialize('foo', 'json');
        $this->assertEquals('"foo"', $result);
    }

    public function testSerializeArrayOfScalars()
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['foo', [5, 3]];
        $result = $serializer->serialize($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testSerializeEmpty()
    {
        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['foo' => new \stdClass()];

        //Old buggy behaviour
        $result = $serializer->serialize($data, 'json');
        $this->assertEquals('{"foo":[]}', $result);

        $result = $serializer->serialize($data, 'json', ['preserve_empty_objects' => true]);
        $this->assertEquals('{"foo":{}}', $result);
    }

    public function testSerializeNoEncoder()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->serialize($data, 'json');
    }

    public function testSerializeNoNormalizer()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\LogicException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->serialize(Model::fromArray($data), 'json');
    }

    public function testDeserialize()
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $result = $serializer->deserialize(json_encode($data), Model::class, 'json');
        $this->assertEquals($data, $result->toArray());
    }

    public function testDeserializeUseCache()
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), Model::class, 'json');
        $data = ['title' => 'bar', 'numbers' => [2, 8]];
        $result = $serializer->deserialize(json_encode($data), Model::class, 'json');
        $this->assertEquals($data, $result->toArray());
    }

    public function testDeserializeNoNormalizer()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\LogicException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), Model::class, 'json');
    }

    public function testDeserializeWrongNormalizer()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([new CustomNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), Model::class, 'json');
    }

    public function testDeserializeNoEncoder()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\UnexpectedValueException::class);
        $serializer = new Serializer([], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), Model::class, 'json');
    }

    public function testDeserializeSupported()
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $this->assertTrue($serializer->supportsDenormalization(json_encode($data), Model::class, 'json'));
    }

    public function testDeserializeNotSupported()
    {
        $serializer = new Serializer([new GetSetMethodNormalizer()], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $this->assertFalse($serializer->supportsDenormalization(json_encode($data), 'stdClass', 'json'));
    }

    public function testDeserializeNotSupportedMissing()
    {
        $serializer = new Serializer([], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $this->assertFalse($serializer->supportsDenormalization(json_encode($data), Model::class, 'json'));
    }

    public function testEncode()
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['foo', [5, 3]];
        $result = $serializer->encode($data, 'json');
        $this->assertEquals(json_encode($data), $result);
    }

    public function testDecode()
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['foo', [5, 3]];
        $result = $serializer->decode(json_encode($data), 'json');
        $this->assertEquals($data, $result);
    }

    public function testSupportsArrayDeserialization()
    {
        $serializer = new Serializer(
            [
                new GetSetMethodNormalizer(),
                new PropertyNormalizer(),
                new ObjectNormalizer(),
                new CustomNormalizer(),
                new ArrayDenormalizer(),
            ],
            [
                'json' => new JsonEncoder(),
            ]
        );

        $this->assertTrue(
            $serializer->supportsDenormalization([], __NAMESPACE__.'\Model[]', 'json')
        );
    }

    public function testDeserializeArray()
    {
        $jsonData = '[{"title":"foo","numbers":[5,3]},{"title":"bar","numbers":[2,8]}]';

        $expectedData = [
            Model::fromArray(['title' => 'foo', 'numbers' => [5, 3]]),
            Model::fromArray(['title' => 'bar', 'numbers' => [2, 8]]),
        ];

        $serializer = new Serializer(
            [
                new GetSetMethodNormalizer(),
                new ArrayDenormalizer(),
            ],
            [
                'json' => new JsonEncoder(),
            ]
        );

        $this->assertEquals(
            $expectedData,
            $serializer->deserialize($jsonData, __NAMESPACE__.'\Model[]', 'json')
        );
    }

    public function testNormalizerAware()
    {
        $normalizerAware = $this->getMockBuilder(NormalizerAwareNormalizer::class)->getMock();
        $normalizerAware->expects($this->once())
            ->method('setNormalizer')
            ->with($this->isInstanceOf(NormalizerInterface::class));

        new Serializer([$normalizerAware]);
    }

    public function testDenormalizerAware()
    {
        $denormalizerAware = $this->getMockBuilder(DenormalizerAwareDenormalizer::class)->getMock();
        $denormalizerAware->expects($this->once())
            ->method('setDenormalizer')
            ->with($this->isInstanceOf(DenormalizerInterface::class));

        new Serializer([$denormalizerAware]);
    }

    public function testDeserializeObjectConstructorWithObjectTypeHint()
    {
        $jsonData = '{"bar":{"value":"baz"}}';

        $serializer = new Serializer([new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $this->assertEquals(new Foo(new Bar('baz')), $serializer->deserialize($jsonData, Foo::class, 'json'));
    }

    public function testDeserializeAndSerializeAbstractObjectsWithTheClassMetadataDiscriminatorResolver()
    {
        $example = new AbstractDummyFirstChild('foo-value', 'bar-value');
        $example->setQuux(new DummyFirstChildQuux('quux'));

        $loaderMock = new class() implements ClassMetadataFactoryInterface {
            public function getMetadataFor($value): ClassMetadataInterface
            {
                if (AbstractDummy::class === $value) {
                    return new ClassMetadata(
                        AbstractDummy::class,
                        new ClassDiscriminatorMapping('type', [
                            'first' => AbstractDummyFirstChild::class,
                            'second' => AbstractDummySecondChild::class,
                        ])
                    );
                }

                throw new InvalidArgumentException();
            }

            public function hasMetadataFor($value): bool
            {
                return AbstractDummy::class === $value;
            }
        };

        $discriminatorResolver = new ClassDiscriminatorFromClassMetadata($loaderMock);
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, new PhpDocExtractor(), $discriminatorResolver)], ['json' => new JsonEncoder()]);

        $jsonData = '{"type":"first","quux":{"value":"quux"},"bar":"bar-value","foo":"foo-value"}';

        $deserialized = $serializer->deserialize($jsonData, AbstractDummy::class, 'json');
        $this->assertEquals($example, $deserialized);

        $serialized = $serializer->serialize($deserialized, 'json');
        $this->assertEquals($jsonData, $serialized);
    }

    public function testDeserializeAndSerializeInterfacedObjectsWithTheClassMetadataDiscriminatorResolver()
    {
        $example = new DummyMessageNumberOne();
        $example->one = 1;

        $jsonData = '{"type":"one","one":1,"two":null}';

        $serializer = $this->serializerWithClassDiscriminator();
        $deserialized = $serializer->deserialize($jsonData, DummyMessageInterface::class, 'json');
        $this->assertEquals($example, $deserialized);

        $serialized = $serializer->serialize($deserialized, 'json');
        $this->assertEquals($jsonData, $serialized);
    }

    public function testDeserializeAndSerializeInterfacedObjectsWithTheClassMetadataDiscriminatorResolverAndGroups()
    {
        $example = new DummyMessageNumberOne();
        $example->two = 2;

        $serializer = $this->serializerWithClassDiscriminator();
        $deserialized = $serializer->deserialize('{"type":"one","one":1,"two":2}', DummyMessageInterface::class, 'json', [
            'groups' => ['two'],
        ]);

        $this->assertEquals($example, $deserialized);

        $serialized = $serializer->serialize($deserialized, 'json', [
            'groups' => ['two'],
        ]);

        $this->assertEquals('{"two":2,"type":"one"}', $serialized);
    }

    public function testDeserializeAndSerializeNestedInterfacedObjectsWithTheClassMetadataDiscriminator()
    {
        $nested = new DummyMessageNumberOne();
        $nested->one = 'foo';

        $example = new DummyMessageNumberTwo();
        $example->setNested($nested);

        $serializer = $this->serializerWithClassDiscriminator();

        $serialized = $serializer->serialize($example, 'json');
        $deserialized = $serializer->deserialize($serialized, DummyMessageInterface::class, 'json');

        $this->assertEquals($example, $deserialized);
    }

    public function testExceptionWhenTypeIsNotKnownInDiscriminator()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\RuntimeException::class);
        $this->expectExceptionMessage('The type "second" has no mapped class for the abstract object "Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface"');
        $this->serializerWithClassDiscriminator()->deserialize('{"type":"second","one":1}', DummyMessageInterface::class, 'json');
    }

    public function testExceptionWhenTypeIsNotInTheBodyToDeserialiaze()
    {
        $this->expectException(\Symfony\Component\Serializer\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Type property "type" not found for the abstract object "Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface"');
        $this->serializerWithClassDiscriminator()->deserialize('{"one":1}', DummyMessageInterface::class, 'json');
    }

    public function testNotNormalizableValueExceptionMessageForAResource()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('An unexpected value could not be normalized: stream resource');

        (new Serializer())->normalize(tmpfile());
    }

    public function testNormalizeTransformEmptyArrayObjectToArray()
    {
        $serializer = new Serializer(
          [
              new PropertyNormalizer(),
              new ObjectNormalizer(),
              new ArrayDenormalizer(),
          ],
          [
              'json' => new JsonEncoder(),
          ]
        );

        $object = [];
        $object['foo'] = new \ArrayObject();
        $object['bar'] = new \ArrayObject(['notempty']);
        $object['baz'] = new \ArrayObject(['nested' => new \ArrayObject()]);

        $this->assertSame('{"foo":[],"bar":["notempty"],"baz":{"nested":[]}}', $serializer->serialize($object, 'json'));
    }

    public function testNormalizePreserveEmptyArrayObject()
    {
        $serializer = new Serializer(
          [
              new PropertyNormalizer(),
              new ObjectNormalizer(),
              new ArrayDenormalizer(),
          ],
          [
              'json' => new JsonEncoder(),
          ]
        );

        $object = [];
        $object['foo'] = new \ArrayObject();
        $object['bar'] = new \ArrayObject(['notempty']);
        $object['baz'] = new \ArrayObject(['nested' => new \ArrayObject()]);
        $this->assertEquals('{"foo":{},"bar":["notempty"],"baz":{"nested":{}}}', $serializer->serialize($object, 'json', [AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true]));
    }

    public function testNormalizeScalar()
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);

        $this->assertSame('42', $serializer->serialize(42, 'json'));
        $this->assertSame('true', $serializer->serialize(true, 'json'));
        $this->assertSame('false', $serializer->serialize(false, 'json'));
        $this->assertSame('3.14', $serializer->serialize(3.14, 'json'));
        $this->assertSame('3.14', $serializer->serialize(31.4e-1, 'json'));
        $this->assertSame('"  spaces  "', $serializer->serialize('  spaces  ', 'json'));
        $this->assertSame('"@Ca$e%"', $serializer->serialize('@Ca$e%', 'json'));
    }

    public function testNormalizeScalarArray()
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);

        $this->assertSame('[42]', $serializer->serialize([42], 'json'));
        $this->assertSame('[true,false]', $serializer->serialize([true, false], 'json'));
        $this->assertSame('[3.14,3.24]', $serializer->serialize([3.14, 32.4e-1], 'json'));
        $this->assertSame('["  spaces  ","@Ca$e%"]', $serializer->serialize(['  spaces  ', '@Ca$e%'], 'json'));
    }

    public function testDeserializeScalar()
    {
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);

        $this->assertSame(42, $serializer->deserialize('42', 'int', 'json'));
        $this->assertTrue($serializer->deserialize('true', 'bool', 'json'));
        $this->assertSame(3.14, $serializer->deserialize('3.14', 'float', 'json'));
        $this->assertSame(3.14, $serializer->deserialize('31.4e-1', 'float', 'json'));
        $this->assertSame('  spaces  ', $serializer->deserialize('"  spaces  "', 'string', 'json'));
        $this->assertSame('@Ca$e%', $serializer->deserialize('"@Ca$e%"', 'string', 'json'));
    }

    public function testDeserializeLegacyScalarType()
    {
        $this->expectException(LogicException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $serializer->deserialize('42', 'integer', 'json');
    }

    public function testDeserializeScalarTypeToCustomType()
    {
        $this->expectException(LogicException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $serializer->deserialize('"something"', Foo::class, 'json');
    }

    public function testDeserializeNonscalarTypeToScalar()
    {
        $this->expectException(NotNormalizableValueException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $serializer->deserialize('{"foo":true}', 'string', 'json');
    }

    public function testDeserializeInconsistentScalarType()
    {
        $this->expectException(NotNormalizableValueException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $serializer->deserialize('"42"', 'int', 'json');
    }

    public function testDeserializeScalarArray()
    {
        $serializer = new Serializer([new ArrayDenormalizer()], ['json' => new JsonEncoder()]);

        $this->assertSame([42], $serializer->deserialize('[42]', 'int[]', 'json'));
        $this->assertSame([true, false], $serializer->deserialize('[true,false]', 'bool[]', 'json'));
        $this->assertSame([3.14, 3.24], $serializer->deserialize('[3.14,32.4e-1]', 'float[]', 'json'));
        $this->assertSame(['  spaces  ', '@Ca$e%'], $serializer->deserialize('["  spaces  ","@Ca$e%"]', 'string[]', 'json'));
    }

    public function testDeserializeInconsistentScalarArray()
    {
        $this->expectException(NotNormalizableValueException::class);
        $serializer = new Serializer([new ArrayDenormalizer()], ['json' => new JsonEncoder()]);
        $serializer->deserialize('["42"]', 'int[]', 'json');
    }

    public function testDeserializeWrappedScalar()
    {
        $serializer = new Serializer([new UnwrappingDenormalizer()], ['json' => new JsonEncoder()]);

        $this->assertSame(42, $serializer->deserialize('{"wrapper": 42}', 'int', 'json', [UnwrappingDenormalizer::UNWRAP_PATH => '[wrapper]']));
    }

    private function serializerWithClassDiscriminator()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));

        return new Serializer([new ObjectNormalizer($classMetadataFactory, null, null, new ReflectionExtractor(), new ClassDiscriminatorFromClassMetadata($classMetadataFactory))], ['json' => new JsonEncoder()]);
    }

    public function testDeserializeAndUnwrap()
    {
        $jsonData = '{"baz": {"foo": "bar", "inner": {"title": "value", "numbers": [5,3]}}}';

        $expectedData = Model::fromArray(['title' => 'value', 'numbers' => [5, 3]]);

        $serializer = new Serializer([new UnwrappingDenormalizer(new PropertyAccessor()), new ObjectNormalizer()], ['json' => new JsonEncoder()]);

        $this->assertEquals(
            $expectedData,
            $serializer->deserialize($jsonData, __NAMESPACE__.'\Model', 'json', [UnwrappingDenormalizer::UNWRAP_PATH => '[baz][inner]'])
        );
    }

    /**
     * @dataProvider provideDenormalizationSuccessResultCases
     */
    public function testDenormalizationSuccessResult(string $type, $normalizedData, $expectedValue)
    {
        $serializer = new Serializer(
            [
                new DateTimeNormalizer(),
                new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
                new ArrayDenormalizer(),
            ],
            [
                'json' => new JsonEncoder(),
            ]
        );

        $json = json_encode($normalizedData);

        $result = $serializer->deserialize($json, $type, 'json', [
            Serializer::COLLECT_INVARIANT_VIOLATIONS => true,
        ]);

        self::assertInstanceOf(DenormalizationResult::class, $result);
        self::assertTrue($result->isSucessful());
        self::assertEquals($expectedValue, $result->getDenormalizedValue());
    }

    public function provideDenormalizationSuccessResultCases()
    {
        $dto = new Dto();
        $dto->int = 1;
        $dto->date = new DateTimeImmutable('2020-01-01');
        $dto->nested = new NestedDto();
        $dto->nested->string = 'string';

        yield [
            Dto::class,
            [
                'int' => 1,
                'date' => '2020-01-01',
                'nested' => [
                    'string' => 'string',
                ],
            ],
            $dto,
        ];

        yield [
            'bool',
            true,
            true,
        ];
    }

    /**
     * @dataProvider provideDenormalizationFailureResultCases
     */
    public function testDenormalizationFailureResult(string $type, $normalizedData, array $expectedErrors)
    {
        $serializer = new Serializer(
            [
                new DateTimeNormalizer(),
                new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
                new ArrayDenormalizer(),
            ],
            [
                'json' => new JsonEncoder(),
            ]
        );

        $json = json_encode($normalizedData);

        $result = $serializer->deserialize($json, $type, 'json', [
            Serializer::COLLECT_INVARIANT_VIOLATIONS => true,
        ]);

        self::assertInstanceOf(DenormalizationResult::class, $result);
        self::assertFalse($result->isSucessful());
        self::assertSame($expectedErrors, $result->getInvariantViolationMessages());
    }

    public function provideDenormalizationFailureResultCases()
    {
        yield [
            Dto::class,
            [
                'int' => 'not-an-integer',
                'date' => 'not-a-date',
                'nested' => [
                    'string' => [],
                ],
            ],
            [
                'int' => ['The type of the "int" attribute for class "Symfony\Component\Serializer\Tests\Dto" must be one of "int" ("string" given).'],
                'date' => ['DateTimeImmutable::__construct(): Failed to parse time string (not-a-date) at position 0 (n): The timezone could not be found in the database'],
                'nested.string' => ['The type of the "string" attribute for class "Symfony\Component\Serializer\Tests\NestedDto" must be one of "string" ("array" given).'],
            ],
        ];

        yield [
            'bool',
            'not-a-boolean',
            [
                '' => ['Data expected to be of type "bool" ("string" given).'],
            ],
        ];
    }

    public function testDenormalizationFailureResultWithUnwrapping()
    {
        $serializer = new Serializer(
            [
                new UnwrappingDenormalizer(new PropertyAccessor()),
                new DateTimeNormalizer(),
                new ObjectNormalizer(null, null, null, new PhpDocExtractor()),
                new ArrayDenormalizer(),
            ],
            [
                'json' => new JsonEncoder(),
            ]
        );

        $json = json_encode([
            'wrapped' => [
                'data' => [
                    'int' => 'not-an-integer',
                    'date' => 'not-a-date',
                    'nested' => [
                        'string' => [],
                    ],
                ],
            ],
        ]);

        $result = $serializer->deserialize($json, Dto::class, 'json', [
            Serializer::COLLECT_INVARIANT_VIOLATIONS => true,
            UnwrappingDenormalizer::UNWRAP_PATH => '[wrapped][data]',
        ]);

        self::assertInstanceOf(DenormalizationResult::class, $result);
        self::assertFalse($result->isSucessful());
        self::assertSame([
            'wrapped.data.int' => ['The type of the "int" attribute for class "Symfony\Component\Serializer\Tests\Dto" must be one of "int" ("string" given).'],
            'wrapped.data.date' => ['DateTimeImmutable::__construct(): Failed to parse time string (not-a-date) at position 0 (n): The timezone could not be found in the database'],
            'wrapped.data.nested.string' => ['The type of the "string" attribute for class "Symfony\Component\Serializer\Tests\NestedDto" must be one of "string" ("array" given).'],
        ], $result->getInvariantViolationMessages());
    }
}

class Model
{
    private $title;
    private $numbers;

    public static function fromArray($array)
    {
        $model = new self();
        if (isset($array['title'])) {
            $model->setTitle($array['title']);
        }
        if (isset($array['numbers'])) {
            $model->setNumbers($array['numbers']);
        }

        return $model;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getNumbers()
    {
        return $this->numbers;
    }

    public function setNumbers($numbers)
    {
        $this->numbers = $numbers;
    }

    public function toArray()
    {
        return ['title' => $this->title, 'numbers' => $this->numbers];
    }
}

class Foo
{
    private $bar;

    public function __construct(Bar $bar)
    {
        $this->bar = $bar;
    }
}

class Bar
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

interface NormalizerAwareNormalizer extends NormalizerInterface, NormalizerAwareInterface
{
}

interface DenormalizerAwareDenormalizer extends DenormalizerInterface, DenormalizerAwareInterface
{
}

class Dto
{
    /**
     * @var int
     */
    public $int;

    /**
     * @var \DateTimeImmutable
     */
    public $date;

    /**
     * @var NestedDto
     */
    public $nested;
}

class NestedDto
{
    /**
     * @var string
     */
    public $string;
}
