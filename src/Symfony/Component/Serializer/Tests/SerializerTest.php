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

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\CustomNormalizer;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeZoneNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyFirstChild;
use Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummySecondChild;
use Symfony\Component\Serializer\Tests\Fixtures\DummyFirstChildQuux;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberOne;
use Symfony\Component\Serializer\Tests\Fixtures\DummyMessageNumberTwo;
use Symfony\Component\Serializer\Tests\Fixtures\DummyObjectWithEnumConstructor;
use Symfony\Component\Serializer\Tests\Fixtures\FalseBuiltInDummy;
use Symfony\Component\Serializer\Tests\Fixtures\NormalizableTraversableDummy;
use Symfony\Component\Serializer\Tests\Fixtures\Php74Full;
use Symfony\Component\Serializer\Tests\Fixtures\Php80WithPromotedTypedConstructor;
use Symfony\Component\Serializer\Tests\Fixtures\TraversableDummy;
use Symfony\Component\Serializer\Tests\Fixtures\TrueBuiltInDummy;
use Symfony\Component\Serializer\Tests\Fixtures\UpcomingDenormalizerInterface as DenormalizerInterface;
use Symfony\Component\Serializer\Tests\Fixtures\UpcomingNormalizerInterface as NormalizerInterface;
use Symfony\Component\Serializer\Tests\Normalizer\TestDenormalizer;
use Symfony\Component\Serializer\Tests\Normalizer\TestNormalizer;

class SerializerTest extends TestCase
{
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
        $this->expectException(UnexpectedValueException::class);
        $serializer = new Serializer([$this->createMock(CustomNormalizer::class)]);
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
        $this->expectException(UnexpectedValueException::class);
        $serializer = new Serializer([new TestDenormalizer()], []);
        $this->assertTrue($serializer->normalize(new \stdClass(), 'json'));
    }

    public function testDenormalizeNoMatch()
    {
        $this->expectException(UnexpectedValueException::class);
        $serializer = new Serializer([$this->createMock(CustomNormalizer::class)]);
        $serializer->denormalize('foo', 'stdClass');
    }

    public function testDenormalizeOnNormalizer()
    {
        $this->expectException(UnexpectedValueException::class);
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
        $normalizer1 = $this->createMock(NormalizerInterface::class);
        $normalizer1->method('getSupportedTypes')->willReturn(['*' => false]);
        $normalizer1->method('supportsNormalization')
            ->willReturnCallback(fn ($data, $format) => isset($data->test));
        $normalizer1->method('normalize')->willReturn('test1');

        $normalizer2 = $this->createMock(NormalizerInterface::class);
        $normalizer2->method('getSupportedTypes')->willReturn(['*' => false]);
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
        $denormalizer1 = $this->createMock(DenormalizerInterface::class);
        $denormalizer1->method('getSupportedTypes')->willReturn(['*' => false]);
        $denormalizer1->method('supportsDenormalization')
            ->willReturnCallback(fn ($data, $type, $format) => isset($data['test1']));
        $denormalizer1->method('denormalize')->willReturn('test1');

        $denormalizer2 = $this->createMock(DenormalizerInterface::class);
        $denormalizer2->method('getSupportedTypes')->willReturn(['*' => false]);
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

        // Old buggy behaviour
        $result = $serializer->serialize($data, 'json');
        $this->assertEquals('{"foo":[]}', $result);

        $result = $serializer->serialize($data, 'json', ['preserve_empty_objects' => true]);
        $this->assertEquals('{"foo":{}}', $result);
    }

    public function testSerializeNoEncoder()
    {
        $this->expectException(UnexpectedValueException::class);
        $serializer = new Serializer([], []);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->serialize($data, 'json');
    }

    public function testSerializeNoNormalizer()
    {
        $this->expectException(LogicException::class);
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
        $this->expectException(LogicException::class);
        $serializer = new Serializer([], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), Model::class, 'json');
    }

    public function testDeserializeWrongNormalizer()
    {
        $this->expectException(UnexpectedValueException::class);
        $serializer = new Serializer([new CustomNormalizer()], ['json' => new JsonEncoder()]);
        $data = ['title' => 'foo', 'numbers' => [5, 3]];
        $serializer->deserialize(json_encode($data), Model::class, 'json');
    }

    public function testDeserializeNoEncoder()
    {
        $this->expectException(UnexpectedValueException::class);
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
        $normalizerAware = $this->createMock(NormalizerAwareNormalizer::class);
        $normalizerAware->expects($this->once())
            ->method('setNormalizer');

        new Serializer([$normalizerAware]);
    }

    public function testDenormalizerAware()
    {
        $denormalizerAware = $this->createMock(DenormalizerAwareDenormalizer::class);
        $denormalizerAware->expects($this->once())
            ->method('setDenormalizer');

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
        try {
            $this->serializerWithClassDiscriminator()->deserialize('{"type":"second","one":1}', DummyMessageInterface::class, 'json');

            $this->fail();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(NotNormalizableValueException::class, $e);
            $this->assertSame('The type "second" is not a valid value.', $e->getMessage());
            $this->assertSame('string', $e->getCurrentType());
            $this->assertSame(['string'], $e->getExpectedTypes());
            $this->assertSame('type', $e->getPath());
            $this->assertTrue($e->canUseMessageForUser());
        }
    }

    public function testExceptionWhenTypeIsNotInTheBodyToDeserialiaze()
    {
        try {
            $this->serializerWithClassDiscriminator()->deserialize('{"one":1}', DummyMessageInterface::class, 'json');

            $this->fail();
        } catch (\Throwable $e) {
            $this->assertInstanceOf(NotNormalizableValueException::class, $e);
            $this->assertSame('Type property "type" not found for the abstract object "Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface".', $e->getMessage());
            $this->assertSame('null', $e->getCurrentType());
            $this->assertSame(['string'], $e->getExpectedTypes());
            $this->assertSame('type', $e->getPath());
            $this->assertFalse($e->canUseMessageForUser());
        }
    }

    public function testNotNormalizableValueExceptionMessageForAResource()
    {
        $this->expectException(NotNormalizableValueException::class);
        $this->expectExceptionMessage('An unexpected value could not be normalized: "stream" resource');

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
        $object['a'] = new \ArrayObject(['nested' => []]);
        $object['b'] = [];

        $this->assertSame('{"foo":[],"bar":["notempty"],"baz":{"nested":[]},"a":{"nested":[]},"b":[]}', $serializer->serialize($object, 'json'));
    }

    public static function provideObjectOrCollectionTests()
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

        $data = [];
        $data['a1'] = new \ArrayObject();
        $data['a2'] = new \ArrayObject(['k' => 'v']);
        $data['b1'] = [];
        $data['b2'] = ['k' => 'v'];
        $data['c1'] = new \ArrayObject(['nested' => new \ArrayObject()]);
        $data['c2'] = new \ArrayObject(['nested' => new \ArrayObject(['k' => 'v'])]);
        $data['d1'] = new \ArrayObject(['nested' => []]);
        $data['d2'] = new \ArrayObject(['nested' => ['k' => 'v']]);
        $data['e1'] = new class() {
            public $map = [];
        };
        $data['e2'] = new class() {
            public $map = ['k' => 'v'];
        };
        $data['f1'] = new class(new \ArrayObject()) {
            public $map;

            public function __construct(\ArrayObject $map)
            {
                $this->map = $map;
            }
        };
        $data['f2'] = new class(new \ArrayObject(['k' => 'v'])) {
            public $map;

            public function __construct(\ArrayObject $map)
            {
                $this->map = $map;
            }
        };

        $data['g1'] = new Baz([]);
        $data['g2'] = new Baz(['greg']);

        yield [$serializer, $data];
    }

    /** @dataProvider provideObjectOrCollectionTests */
    public function testNormalizeWithCollection(Serializer $serializer, array $data)
    {
        $expected = '{"a1":[],"a2":{"k":"v"},"b1":[],"b2":{"k":"v"},"c1":{"nested":[]},"c2":{"nested":{"k":"v"}},"d1":{"nested":[]},"d2":{"nested":{"k":"v"}},"e1":{"map":[]},"e2":{"map":{"k":"v"}},"f1":{"map":[]},"f2":{"map":{"k":"v"}},"g1":{"list":[],"settings":[]},"g2":{"list":["greg"],"settings":[]}}';
        $this->assertSame($expected, $serializer->serialize($data, 'json'));
    }

    /** @dataProvider provideObjectOrCollectionTests */
    public function testNormalizePreserveEmptyArrayObject(Serializer $serializer, array $data)
    {
        $expected = '{"a1":{},"a2":{"k":"v"},"b1":[],"b2":{"k":"v"},"c1":{"nested":{}},"c2":{"nested":{"k":"v"}},"d1":{"nested":[]},"d2":{"nested":{"k":"v"}},"e1":{"map":[]},"e2":{"map":{"k":"v"}},"f1":{"map":{}},"f2":{"map":{"k":"v"}},"g1":{"list":{},"settings":[]},"g2":{"list":["greg"],"settings":[]}}';
        $this->assertSame($expected, $serializer->serialize($data, 'json', [
            AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
        ]));
    }

    /** @dataProvider provideObjectOrCollectionTests */
    public function testNormalizeEmptyArrayAsObject(Serializer $serializer, array $data)
    {
        $expected = '{"a1":[],"a2":{"k":"v"},"b1":{},"b2":{"k":"v"},"c1":{"nested":[]},"c2":{"nested":{"k":"v"}},"d1":{"nested":{}},"d2":{"nested":{"k":"v"}},"e1":{"map":{}},"e2":{"map":{"k":"v"}},"f1":{"map":[]},"f2":{"map":{"k":"v"}},"g1":{"list":[],"settings":{}},"g2":{"list":["greg"],"settings":{}}}';
        $this->assertSame($expected, $serializer->serialize($data, 'json', [
            Serializer::EMPTY_ARRAY_AS_OBJECT => true,
        ]));
    }

    /** @dataProvider provideObjectOrCollectionTests */
    public function testNormalizeEmptyArrayAsObjectAndPreserveEmptyArrayObject(Serializer $serializer, array $data)
    {
        $expected = '{"a1":{},"a2":{"k":"v"},"b1":{},"b2":{"k":"v"},"c1":{"nested":{}},"c2":{"nested":{"k":"v"}},"d1":{"nested":{}},"d2":{"nested":{"k":"v"}},"e1":{"map":{}},"e2":{"map":{"k":"v"}},"f1":{"map":{}},"f2":{"map":{"k":"v"}},"g1":{"list":{},"settings":{}},"g2":{"list":["greg"],"settings":{}}}';
        $this->assertSame($expected, $serializer->serialize($data, 'json', [
            Serializer::EMPTY_ARRAY_AS_OBJECT => true,
            AbstractObjectNormalizer::PRESERVE_EMPTY_OBJECTS => true,
        ]));
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

    public function testUnionTypeDeserializable()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $serializer = new Serializer(
            [
                new DateTimeNormalizer(),
                new ObjectNormalizer($classMetadataFactory, null, null, $extractor, new ClassDiscriminatorFromClassMetadata($classMetadataFactory)),
            ],
            ['json' => new JsonEncoder()]
        );

        $actual = $serializer->deserialize('{ "changed": null }', DummyUnionType::class, 'json', [
            DateTimeNormalizer::FORMAT_KEY => \DateTime::ISO8601,
        ]);

        $this->assertEquals((new DummyUnionType())->setChanged(null), $actual, 'Union type denormalization first case failed.');

        $actual = $serializer->deserialize('{ "changed": "2022-03-22T16:15:05+0000" }', DummyUnionType::class, 'json', [
            DateTimeNormalizer::FORMAT_KEY => \DateTime::ISO8601,
        ]);

        $expectedDateTime = \DateTime::createFromFormat(\DateTime::ISO8601, '2022-03-22T16:15:05+0000');
        $this->assertEquals((new DummyUnionType())->setChanged($expectedDateTime), $actual, 'Union type denormalization second case failed.');

        $actual = $serializer->deserialize('{ "changed": false }', DummyUnionType::class, 'json', [
            DateTimeNormalizer::FORMAT_KEY => \DateTime::ISO8601,
        ]);

        $this->assertEquals(new DummyUnionType(), $actual, 'Union type denormalization third case failed.');
    }

    public function testUnionTypeDeserializableWithoutAllowedExtraAttributes()
    {
        $classMetadataFactory = new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()));
        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);
        $serializer = new Serializer(
            [
                new ObjectNormalizer($classMetadataFactory, null, null, $extractor, new ClassDiscriminatorFromClassMetadata($classMetadataFactory)),
            ],
            ['json' => new JsonEncoder()]
        );

        $actual = $serializer->deserialize('{ "v": { "a": 0 }}', DummyUnionWithAAndCAndB::class, 'json', [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
        ]);

        $this->assertEquals(new DummyUnionWithAAndCAndB(new DummyATypeForUnion()), $actual);

        $actual = $serializer->deserialize('{ "v": { "b": 1 }}', DummyUnionWithAAndCAndB::class, 'json', [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
        ]);

        $this->assertEquals(new DummyUnionWithAAndCAndB(new DummyBTypeForUnion()), $actual);

        $actual = $serializer->deserialize('{ "v": { "c": 3 }}', DummyUnionWithAAndCAndB::class, 'json', [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
        ]);

        $this->assertEquals(new DummyUnionWithAAndCAndB(new DummyCTypeForUnion(3)), $actual);

        $this->expectException(ExtraAttributesException::class);
        $serializer->deserialize('{ "v": { "b": 1, "d": "i am not allowed" }}', DummyUnionWithAAndCAndB::class, 'json', [
            AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
        ]);
    }

    /**
     * @requires PHP 8.2
     */
    public function testFalseBuiltInTypes()
    {
        $extractor = new PropertyInfoExtractor([], [new ReflectionExtractor()]);
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, $extractor)], ['json' => new JsonEncoder()]);

        $actual = $serializer->deserialize('{"false":false}', FalseBuiltInDummy::class, 'json');

        $this->assertEquals(new FalseBuiltInDummy(), $actual);
    }

    /**
     * @requires PHP 8.2
     */
    public function testTrueBuiltInTypes()
    {
        $extractor = new PropertyInfoExtractor([], [new ReflectionExtractor()]);
        $serializer = new Serializer([new ObjectNormalizer(null, null, null, $extractor)], ['json' => new JsonEncoder()]);

        $actual = $serializer->deserialize('{"true":true}', TrueBuiltInDummy::class, 'json');

        $this->assertEquals(new TrueBuiltInDummy(), $actual);
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
     * @dataProvider provideCollectDenormalizationErrors
     */
    public function testCollectDenormalizationErrors(?ClassMetadataFactory $classMetadataFactory)
    {
        $json = '
        {
            "string": null,
            "int": null,
            "float": null,
            "bool": null,
            "dateTime": null,
            "dateTimeImmutable": null,
            "dateTimeZone": null,
            "splFileInfo": null,
            "uuid": null,
            "array": null,
            "collection": [
                {
                    "string": "string"
                },
                {
                    "string": null
                }
            ],
            "php74FullWithConstructor": {},
            "php74FullWithTypedConstructor": {
                "something": "not a float"
            },
            "dummyMessage": {
            },
            "nestedObject": {
                "int": "string"
            },
            "anotherCollection": null
        }';

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        $serializer = new Serializer(
            [
                new ArrayDenormalizer(),
                new DateTimeNormalizer(),
                new DateTimeZoneNormalizer(),
                new DataUriNormalizer(),
                new UidNormalizer(),
                new ObjectNormalizer($classMetadataFactory, null, null, $extractor, $classMetadataFactory ? new ClassDiscriminatorFromClassMetadata($classMetadataFactory) : null),
            ],
            ['json' => new JsonEncoder()]
        );

        try {
            $serializer->deserialize($json, Php74Full::class, 'json', [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);

            $this->fail();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(PartialDenormalizationException::class, $th);
        }

        $this->assertInstanceOf(Php74Full::class, $th->getData());

        $exceptionsAsArray = array_map(fn (NotNormalizableValueException $e): array => [
            'currentType' => $e->getCurrentType(),
            'expectedTypes' => $e->getExpectedTypes(),
            'path' => $e->getPath(),
            'useMessageForUser' => $e->canUseMessageForUser(),
            'message' => $e->getMessage(),
        ], $th->getErrors());

        $expected = [
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => 'string',
                'useMessageForUser' => false,
                'message' => 'The type of the "string" attribute for class "Symfony\\Component\\Serializer\\Tests\\Fixtures\\Php74Full" must be one of "string" ("null" given).',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'int',
                ],
                'path' => 'int',
                'useMessageForUser' => false,
                'message' => 'The type of the "int" attribute for class "Symfony\\Component\\Serializer\\Tests\\Fixtures\\Php74Full" must be one of "int" ("null" given).',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'float',
                ],
                'path' => 'float',
                'useMessageForUser' => false,
                'message' => 'The type of the "float" attribute for class "Symfony\\Component\\Serializer\\Tests\\Fixtures\\Php74Full" must be one of "float" ("null" given).',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'bool',
                ],
                'path' => 'bool',
                'useMessageForUser' => false,
                'message' => 'The type of the "bool" attribute for class "Symfony\\Component\\Serializer\\Tests\\Fixtures\\Php74Full" must be one of "bool" ("null" given).',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => 'dateTime',
                'useMessageForUser' => true,
                'message' => 'The data is either not an string, an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => 'dateTimeImmutable',
                'useMessageForUser' => true,
                'message' => 'The data is either not an string, an empty string, or null; you should pass a string that can be parsed with the passed format or a valid DateTime string.',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => 'dateTimeZone',
                'useMessageForUser' => true,
                'message' => 'The data is either an empty string or null, you should pass a string that can be parsed as a DateTimeZone.',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => 'splFileInfo',
                'useMessageForUser' => true,
                'message' => 'The provided "data:" URI is not valid.',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => 'uuid',
                'useMessageForUser' => true,
                'message' => 'The data is not a valid "Symfony\Component\Uid\Uuid" string representation.',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'array',
                ],
                'path' => 'array',
                'useMessageForUser' => false,
                'message' => 'The type of the "array" attribute for class "Symfony\\Component\\Serializer\\Tests\\Fixtures\\Php74Full" must be one of "array" ("null" given).',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => 'collection[1].string',
                'useMessageForUser' => false,
                'message' => 'The type of the "string" attribute for class "Symfony\Component\Serializer\Tests\Fixtures\Php74Full" must be one of "string" ("null" given).',
            ],
            [
                'currentType' => 'array',
                'expectedTypes' => [
                    'unknown',
                ],
                'path' => 'php74FullWithConstructor',
                'useMessageForUser' => true,
                'message' => 'Failed to create object because the class misses the "constructorArgument" property.',
            ],
            [
                'currentType' => 'string',
                'expectedTypes' => [
                    'float',
                ],
                'path' => 'php74FullWithTypedConstructor.something',
                'useMessageForUser' => false,
                'message' => 'The type of the "something" attribute for class "Symfony\Component\Serializer\Tests\Fixtures\Php74FullWithTypedConstructor" must be one of "float" ("string" given).',
            ],
            $classMetadataFactory ?
                [
                    'currentType' => 'null',
                    'expectedTypes' => [
                        'string',
                    ],
                    'path' => 'dummyMessage.type',
                    'useMessageForUser' => false,
                    'message' => 'Type property "type" not found for the abstract object "Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface".',
                ] :
                [
                    'currentType' => 'array',
                    'expectedTypes' => [
                        DummyMessageInterface::class,
                    ],
                    'path' => 'dummyMessage',
                    'useMessageForUser' => false,
                    'message' => 'The type of the "dummyMessage" attribute for class "Symfony\Component\Serializer\Tests\Fixtures\Php74Full" must be one of "Symfony\Component\Serializer\Tests\Fixtures\DummyMessageInterface" ("array" given).',
                ],
            [
                'currentType' => 'string',
                'expectedTypes' => [
                    'int',
                ],
                'path' => 'nestedObject[int]',
                'useMessageForUser' => true,
                'message' => 'The type of the key "int" must be "int" ("string" given).',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => ['array'],
                'path' => 'anotherCollection',
                'useMessageForUser' => false,
                'message' => 'Data expected to be "Symfony\Component\Serializer\Tests\Fixtures\Php74Full[]", "null" given.',
            ],
        ];

        $this->assertSame($expected, $exceptionsAsArray);
    }

    /**
     * @dataProvider provideCollectDenormalizationErrors
     */
    public function testCollectDenormalizationErrors2(?ClassMetadataFactory $classMetadataFactory)
    {
        $json = '
        [
            {
                "string": null
            },
            {
                "string": null
            }
        ]';

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        $serializer = new Serializer(
            [
                new ArrayDenormalizer(),
                new ObjectNormalizer($classMetadataFactory, null, null, $extractor, $classMetadataFactory ? new ClassDiscriminatorFromClassMetadata($classMetadataFactory) : null),
            ],
            ['json' => new JsonEncoder()]
        );

        try {
            $serializer->deserialize($json, Php74Full::class.'[]', 'json', [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);

            $this->fail();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(PartialDenormalizationException::class, $th);
        }

        $this->assertCount(2, $th->getData());
        $this->assertInstanceOf(Php74Full::class, $th->getData()[0]);
        $this->assertInstanceOf(Php74Full::class, $th->getData()[1]);

        $exceptionsAsArray = array_map(fn (NotNormalizableValueException $e): array => [
            'currentType' => $e->getCurrentType(),
            'expectedTypes' => $e->getExpectedTypes(),
            'path' => $e->getPath(),
            'useMessageForUser' => $e->canUseMessageForUser(),
            'message' => $e->getMessage(),
        ], $th->getErrors());

        $expected = [
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => '[0].string',
                'useMessageForUser' => false,
                'message' => 'The type of the "string" attribute for class "Symfony\\Component\\Serializer\\Tests\\Fixtures\\Php74Full" must be one of "string" ("null" given).',
            ],
            [
                'currentType' => 'null',
                'expectedTypes' => [
                    'string',
                ],
                'path' => '[1].string',
                'useMessageForUser' => false,
                'message' => 'The type of the "string" attribute for class "Symfony\\Component\\Serializer\\Tests\\Fixtures\\Php74Full" must be one of "string" ("null" given).',
            ],
            ];

        $this->assertSame($expected, $exceptionsAsArray);
    }

    /**
     * @dataProvider provideCollectDenormalizationErrors
     */
    public function testCollectDenormalizationErrorsWithConstructor(?ClassMetadataFactory $classMetadataFactory)
    {
        $json = '{"bool": "bool"}';

        $extractor = new PropertyInfoExtractor([], [new PhpDocExtractor(), new ReflectionExtractor()]);

        $serializer = new Serializer(
            [
                new ObjectNormalizer($classMetadataFactory, null, null, $extractor, $classMetadataFactory ? new ClassDiscriminatorFromClassMetadata($classMetadataFactory) : null),
            ],
            ['json' => new JsonEncoder()]
        );

        try {
            $serializer->deserialize($json, Php80WithPromotedTypedConstructor::class, 'json', [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);

            $this->fail();
        } catch (\Throwable $th) {
            $this->assertInstanceOf(PartialDenormalizationException::class, $th);
        }

        $this->assertInstanceOf(Php80WithPromotedTypedConstructor::class, $th->getData());

        $exceptionsAsArray = array_map(fn (NotNormalizableValueException $e): array => [
            'currentType' => $e->getCurrentType(),
            'expectedTypes' => $e->getExpectedTypes(),
            'path' => $e->getPath(),
            'useMessageForUser' => $e->canUseMessageForUser(),
            'message' => $e->getMessage(),
        ], $th->getErrors());

        $expected = [
            [
                'currentType' => 'string',
                'expectedTypes' => [
                    'bool',
                ],
                'path' => 'bool',
                'useMessageForUser' => false,
                'message' => 'The type of the "bool" attribute for class "Symfony\\Component\\Serializer\\Tests\\Fixtures\\Php80WithPromotedTypedConstructor" must be one of "bool" ("string" given).',
            ],
        ];

        $this->assertSame($expected, $exceptionsAsArray);
    }

    public function testCollectDenormalizationErrorsWithEnumConstructor()
    {
        $serializer = new Serializer(
            [
                new BackedEnumNormalizer(),
                new ObjectNormalizer(),
            ],
            ['json' => new JsonEncoder()]
        );

        try {
            $serializer->deserialize('{"invalid": "GET"}', DummyObjectWithEnumConstructor::class, 'json', [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (\Throwable $th) {
            $this->assertInstanceOf(PartialDenormalizationException::class, $th);
        }

        $exceptionsAsArray = array_map(fn (NotNormalizableValueException $e): array => [
            'currentType' => $e->getCurrentType(),
            'useMessageForUser' => $e->canUseMessageForUser(),
            'message' => $e->getMessage(),
        ], $th->getErrors());

        $expected = [
            [
                'currentType' => 'array',
                'useMessageForUser' => true,
                'message' => 'Failed to create object because the class misses the "get" property.',
            ],
        ];

        $this->assertSame($expected, $exceptionsAsArray);
    }

    public function testNoCollectDenormalizationErrorsWithWrongEnum()
    {
        $serializer = new Serializer(
            [
                new BackedEnumNormalizer(),
                new ObjectNormalizer(),
            ],
            ['json' => new JsonEncoder()]
        );

        try {
            $serializer->deserialize('{"get": "invalid"}', DummyObjectWithEnumConstructor::class, 'json', [
                DenormalizerInterface::COLLECT_DENORMALIZATION_ERRORS => true,
            ]);
        } catch (\Throwable $th) {
            $this->assertNotInstanceOf(PartialDenormalizationException::class, $th);
            $this->assertInstanceOf(InvalidArgumentException::class, $th);
        }
    }

    public static function provideCollectDenormalizationErrors()
    {
        return [
            [null],
            [new ClassMetadataFactory(new AnnotationLoader(new AnnotationReader()))],
        ];
    }

    public function testSerializerUsesSupportedTypesMethod()
    {
        $neverCalledNormalizer = $this->createMock(DummyNormalizer::class);
        $neverCalledNormalizer
            // once for normalization, once for denormalization
            ->expects($this->exactly(2))
            ->method('getSupportedTypes')
            ->willReturn([
                Foo::class => true,
                Bar::class => false,
            ]);

        $supportedAndCachedNormalizer = $this->createMock(DummyNormalizer::class);
        $supportedAndCachedNormalizer
            // once for normalization, once for denormalization
            ->expects($this->exactly(2))
            ->method('getSupportedTypes')
            ->willReturn([
                Model::class => true,
            ]);

        $serializer = new Serializer(
            [
                $neverCalledNormalizer,
                $supportedAndCachedNormalizer,
                new ObjectNormalizer(),
            ],
            ['json' => new JsonEncoder()]
        );

        // Normalization process
        $neverCalledNormalizer
            ->expects($this->never())
            ->method('supportsNormalization');
        $neverCalledNormalizer
            ->expects($this->never())
            ->method('normalize');

        $supportedAndCachedNormalizer
            ->expects($this->once())
            ->method('supportsNormalization')
            ->willReturn(true);
        $supportedAndCachedNormalizer
            ->expects($this->exactly(2))
            ->method('normalize')
            ->willReturn(['foo' => 'bar']);

        $serializer->normalize(new Model(), 'json');
        $serializer->normalize(new Model(), 'json');

        // Denormalization pass
        $neverCalledNormalizer
            ->expects($this->never())
            ->method('supportsDenormalization');
        $neverCalledNormalizer
            ->expects($this->never())
            ->method('denormalize');
        $supportedAndCachedNormalizer
            ->expects($this->once())
            ->method('supportsDenormalization')
            ->willReturn(true);
        $supportedAndCachedNormalizer
            ->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturn(new Model());

        $serializer->denormalize('foo', Model::class, 'json');
        $serializer->denormalize('foo', Model::class, 'json');
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

class DummyUnionType
{
    /**
     * @var \DateTime|bool|null
     */
    public $changed = false;

    /**
     * @param \DateTime|bool|null
     *
     * @return $this
     */
    public function setChanged($changed): static
    {
        $this->changed = $changed;

        return $this;
    }
}

class DummyATypeForUnion
{
    public $a = 0;
}

class DummyBTypeForUnion
{
    public $b = 1;
}

class DummyCTypeForUnion
{
    public $c = 2;

    public function __construct($c)
    {
        $this->c = $c;
    }
}

class DummyUnionWithAAndCAndB
{
    /** @var DummyATypeForUnion|DummyCTypeForUnion|DummyBTypeForUnion */
    public $v;

    /**
     * @param DummyATypeForUnion|DummyCTypeForUnion|DummyBTypeForUnion $v
     */
    public function __construct($v)
    {
        $this->v = $v;
    }
}

class Baz
{
    public $list;

    public $settings = [];

    public function __construct(array $list)
    {
        $this->list = new DummyList($list);
    }
}

class DummyList extends \ArrayObject
{
    public $list;

    public function __construct(array $list)
    {
        $this->list = $list;

        $this->setFlags(\ArrayObject::STD_PROP_LIST);
    }

    public function count(): int
    {
        return \count($this->list);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->list);
    }
}

abstract class DummyNormalizer implements NormalizerInterface, DenormalizerInterface
{
    abstract public function getSupportedTypes(?string $format): array;
}

interface NormalizerAwareNormalizer extends NormalizerInterface, NormalizerAwareInterface
{
}

interface DenormalizerAwareDenormalizer extends DenormalizerInterface, DenormalizerAwareInterface
{
}
