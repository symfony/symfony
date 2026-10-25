<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\JsonStreamer\Exception\InvalidStreamException;
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\JsonStreamer\JsonStreamReader;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadata;
use Symfony\Component\JsonStreamer\Mapping\PropertyMetadataLoaderInterface;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Mapping\SyntheticPropertyMetadataLoader;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBcMathNumber;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithConstructor;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateIntervals;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimeZones;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDummiesAndDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDummyAndDummies;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithGenericDummies;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithGenerics;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithGmpNumber;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithIterable;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullablePropertyWithDefault;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableRepeatedDummies;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNulName;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNumericStreamedObject;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithPhpDoc;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithPrivateConstructor;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithQuotes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithRequiredConstructor;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUids;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueObjects;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\DivideStringAndCastToIntValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\HeightValueObjectTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\StringToBooleanValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueObject\Height;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

class JsonStreamReaderTest extends TestCase
{
    private string $streamReadersDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamReadersDir = \sprintf('%s/symfony_json_streamer_test/stream_reader', sys_get_temp_dir());

        if (is_dir($this->streamReadersDir)) {
            array_map('unlink', glob($this->streamReadersDir.'/*'));
            rmdir($this->streamReadersDir);
        }
    }

    public function testReadScalar()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, null, 'null', Type::nullable(Type::int()));
        $this->assertRead($reader, true, 'true', Type::bool());
        $this->assertRead($reader, [['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::builtin(TypeIdentifier::ARRAY));
        $this->assertRead($reader, [['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::builtin(TypeIdentifier::ITERABLE));
        $this->assertRead($reader, (object) ['foo' => 'bar'], '{"foo": "bar"}', Type::object());
        $this->assertRead($reader, DummyBackedEnum::ONE, '1', Type::enum(DummyBackedEnum::class, Type::string()));
    }

    #[DataProvider('invalidCollectionElementDataProvider')]
    public function testReadCollectionWithInvalidElementFromStream(string $json)
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $json);
        rewind($resource);

        $this->expectException(UnexpectedValueException::class);

        $reader->read($resource, Type::list(Type::int()));
    }

    public static function invalidCollectionElementDataProvider(): iterable
    {
        yield ['[1 0]'];
        yield ['[_]'];
        yield ['[[1}]'];
    }

    #[DataProvider('invalidUnknownPropertyDataProvider')]
    public function testReadObjectWithInvalidUnknownPropertyFromStream(string $json)
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $json);
        rewind($resource);

        $this->expectException(UnexpectedValueException::class);

        $reader->read($resource, Type::object(ClassicDummy::class))->id;
    }

    public static function invalidUnknownPropertyDataProvider(): iterable
    {
        yield ['{"id":1,"extra":[1 2 _],"name":"x"}'];
        yield ['{"id":1,"extra":[},"name":"x"}'];
        yield 'unpaired surrogate' => ['{"id":1,"extra":"\uD800","name":"x"}'];
    }

    public function testRejectsUnknownPropertyWithInvalidUnicodeEscapeFromStream()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $resource = fopen('php://temp', 'w');
        fwrite($resource, '{"id":1,"extra":"\uD800"}');
        rewind($resource);

        $this->expectException(UnexpectedValueException::class);

        $reader->read($resource, Type::object(ClassicDummy::class))->id;
    }

    public function testReadCollection()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead(
            $reader,
            [true, false],
            '{"0": true, "1": false}',
            Type::array(Type::bool()),
        );

        $this->assertRead(
            $reader,
            [true, false],
            '[true, false]',
            Type::array(Type::bool()),
        );

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame([1, 2], array_map(static fn (ClassicDummy $d): int => $d->id, $read));
        }, '[{"id":1,"name":"a"},{"id":2,"name":"b"}]', Type::array(Type::object(ClassicDummy::class)));

        $this->assertRead(
            $reader,
            [true, false],
            '[true, false]',
            Type::list(Type::bool()),
        );

        $this->assertRead($reader, function (mixed $read) {
            $this->assertIsIterable($read);
            $this->assertSame([true, false], iterator_to_array($read));
        }, '{"0": true, "1": false}', Type::iterable(Type::bool()));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertIsIterable($read);
            $this->assertSame([true, false], iterator_to_array($read));
        }, '[true, false]', Type::iterable(Type::bool()));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertIsIterable($read);
            $this->assertSame([true, false], iterator_to_array($read));
        }, '[true, false]', Type::iterable(Type::bool(), Type::int()));
    }

    public function testReadArrayShapeWithQuotedKey()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $type = Type::arrayShape(["it's" => Type::int(), "a\\'b" => Type::int()]);

        $this->assertRead($reader, ["it's" => 1, "a\\'b" => 2], '{"it\'s": 1, "a\\\\\'b": 2}', $type);
    }

    public function testReadObjectWithQuotedStreamedNameAndValueTransformerId()
    {
        $reader = JsonStreamReader::create(["divide'it" => new DivideStringAndCastToIntValueTransformer()], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithQuotes::class, $read);
            $this->assertSame(5, $read->name);
            $this->assertSame(10, $read->transformed);
        }, '{"it\'s \\\\\' quoted": 5, "transformed": "20"}', Type::object(DummyWithQuotes::class), ['scale' => 1]);
    }

    public function testReadObjectWithQuotedPropertyName()
    {
        $propertyMetadataLoader = new class implements PropertyMetadataLoaderInterface {
            public function load(string $className, array $options = [], array $context = []): array
            {
                return ['key' => new PropertyMetadata("a'b\\c", Type::int())];
            }
        };
        $reader = new JsonStreamReader(new Container(), $propertyMetadataLoader, $this->streamReadersDir);

        $read = $reader->read('{"key":7}', Type::object(\stdClass::class));

        $this->assertSame(["a'b\\c" => 7], get_object_vars($read));
    }

    public function testReadTypeWithUnsupportedCharacters()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $type = Type::arrayShape(['foo*/bar' => Type::int()]);

        $this->assertRead($reader, ['foo*/bar' => 1], '{"foo*/bar": 1}', $type);
    }

    public function testReadObject()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(ClassicDummy::class, $read);
            $this->assertSame(10, $read->id);
            $this->assertSame('dummy name', $read->name);
        }, '{"id": 10, "name": "dummy name"}', Type::object(ClassicDummy::class));
    }

    public function testReadObjectWithGenerics()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithGenerics::class, $read);
            $this->assertSame(10, $read->dummies[0]->id);
            $this->assertSame('dummy name', $read->dummies[0]->name);
        }, '{"dummies":[{"id":10,"name":"dummy name"}]}', Type::generic(Type::object(DummyWithGenerics::class), Type::object(ClassicDummy::class)));
    }

    public function testReadObjectWithStreamedName()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithNameAttributes::class, $read);
            $this->assertSame(10, $read->id);
        }, '{"@id": 10}', Type::object(DummyWithNameAttributes::class));
    }

    public function testReadObjectWithValueTransformer()
    {
        $reader = JsonStreamReader::create([
            StringToBooleanValueTransformer::class => new StringToBooleanValueTransformer(),
            DivideStringAndCastToIntValueTransformer::class => new DivideStringAndCastToIntValueTransformer(),
        ], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithValueTransformerAttributes::class, $read);
            $this->assertSame(10, $read->id);
            $this->assertTrue($read->active);
            $this->assertSame('LOWERCASE NAME', $read->name);
            $this->assertSame([0, 1], $read->range);
        }, '{"id": "20", "active": "true", "name": "lowercase name", "range": "0..1"}', Type::object(DummyWithValueTransformerAttributes::class), ['scale' => 1]);
    }

    public function testReadObjectWithValueObjects()
    {
        $reader = JsonStreamReader::create([
            Height::class => new HeightValueObjectTransformer(),
        ], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithValueObjects::class, $read);
            $this->assertEquals(new Height(10, 'm'), $read->height);
            $this->assertEquals(new Height(10, 'dm'), $read->nullableHeight);
            $this->assertEquals(new Height(10, 'cm'), $read->unionHeight);
        }, '{"height":"10 m","nullableHeight":"10 dm","unionHeight":"10 cm"}', Type::object(DummyWithValueObjects::class));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithValueObjects::class, $read);
            $this->assertEquals(new Height(10, 'm'), $read->height);
            $this->assertNull($read->nullableHeight);
            $this->assertSame(10, $read->unionHeight);
        }, '{"height":"10 m","nullableHeight":null,"unionHeight":10}', Type::object(DummyWithValueObjects::class));
    }

    public function testReadObjectWithValueTransformerAndMissingProperty()
    {
        $reader = JsonStreamReader::create([
            StringToBooleanValueTransformer::class => new StringToBooleanValueTransformer(),
            DivideStringAndCastToIntValueTransformer::class => new DivideStringAndCastToIntValueTransformer(),
        ], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithValueTransformerAttributes::class, $read);
            $this->assertSame(1, $read->id);
            $this->assertTrue($read->active);
            $this->assertSame('DUMMY', $read->name);
            $this->assertSame([0, 1], $read->range);
        }, '{"active": "true", "range": "0..1"}', Type::object(DummyWithValueTransformerAttributes::class), ['scale' => 1]);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame(1, $read->id);
            $this->assertFalse($read->active);
            $this->assertSame('DUMMY', $read->name);
            $this->assertSame([10, 20], $read->range);
        }, '{}', Type::object(DummyWithValueTransformerAttributes::class), ['scale' => 1]);
    }

    public function testReadObjectWithValueTransformerAndNullProperty()
    {
        $reader = JsonStreamReader::create([
            StringToBooleanValueTransformer::class => new StringToBooleanValueTransformer(),
            DivideStringAndCastToIntValueTransformer::class => new DivideStringAndCastToIntValueTransformer(),
        ], $this->streamReadersDir);

        $read = $reader->read('{"id": "20", "active": "true", "name": null, "range": "0..1"}', Type::object(DummyWithValueTransformerAttributes::class), ['scale' => 1]);
        $this->assertSame(10, $read->id);
        $this->assertSame('DUMMY', $read->name);
    }

    public function testReadObjectWithBackedEnumValueTransformerAndMissingProperty()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame('DEFAULT', $read->enum);
            $this->assertSame('DEFAULT', $read->nullableEnum);
            $this->assertFalse((new \ReflectionProperty($read, 'requiredEnum'))->isInitialized($read));
        }, '{}', Type::object(DummyWithBackedEnumValueTransformer::class));
    }

    public function testReadObjectWithBackedEnumValueTransformerAndMissingNullableProperty()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame('TWO', $read->enum);
            $this->assertSame('DEFAULT', $read->nullableEnum);
            $this->assertSame('TWO', $read->requiredEnum);
        }, '{"enum": 2, "requiredEnum": 2}', Type::object(DummyWithBackedEnumValueTransformer::class));
    }

    public function testReadObjectWithBackedEnumValueTransformerAndMissingRequiredProperty()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame('TWO', $read->enum);
            $this->assertSame('TWO', $read->nullableEnum);
            $this->assertFalse((new \ReflectionProperty($read, 'requiredEnum'))->isInitialized($read));
        }, '{"enum": 2, "nullableEnum": 2}', Type::object(DummyWithBackedEnumValueTransformer::class));
    }

    public function testReadObjectWithBackedEnumValueTransformer()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame('TWO', $read->enum);
            $this->assertSame('TWO', $read->nullableEnum);
            $this->assertSame('TWO', $read->requiredEnum);
        }, '{"enum": 2, "nullableEnum": 2, "requiredEnum": 2}', Type::object(DummyWithBackedEnumValueTransformer::class));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame('TWO', $read->enum);
            $this->assertNull($read->nullableEnum);
            $this->assertSame('TWO', $read->requiredEnum);
        }, '{"enum": 2, "nullableEnum": null, "requiredEnum": 2}', Type::object(DummyWithBackedEnumValueTransformer::class));
    }

    public function testReadObjectWithPhpDoc()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithPhpDoc::class, $read);
            $this->assertIsArray($read->arrayOfDummies);
            $this->assertContainsOnlyInstancesOf(DummyWithNameAttributes::class, $read->arrayOfDummies);
            $this->assertArrayHasKey('key', $read->arrayOfDummies);
        }, '{"arrayOfDummies":{"key":{"@id":10,"name":"dummy"}}}', Type::object(DummyWithPhpDoc::class));
    }

    public function testReadObjectWithNullableProperties()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithNullableProperties::class, $read);
            $this->assertNull($read->name);
            $this->assertNull($read->enum);
        }, '{"name":null,"enum":null}', Type::object(DummyWithNullableProperties::class));
    }

    public function testReadObjectKeepsDefaultValueOfMissingProperties()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame(1, $read->id);
            $this->assertSame('dummy', $read->name);
        }, '{}', Type::object(ClassicDummy::class));
    }

    public function testReadObjectKeepsDefaultValueOfNullProperties()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $read = $reader->read('{"id":null,"name":"name"}', Type::object(ClassicDummy::class));
        $this->assertSame(1, $read->id);
        $this->assertSame('name', $read->name);
    }

    public function testReadObjectPreservesMissingValueSentinel()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertSame('_symfony_missing_value', $read->name);
        }, '{"name":"_symfony_missing_value"}', Type::object(ClassicDummy::class));
    }

    public function testReadObjectWithInvalidPropertyType()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        try {
            $reader->read('{"id":"not an int"}', Type::object(ClassicDummy::class));
            $this->fail(\sprintf('Expected "%s" to be thrown.', UnexpectedValueException::class));
        } catch (UnexpectedValueException $e) {
            $this->assertStringContainsString(ClassicDummy::class.'::$id of type int', $e->getMessage());
            $this->assertInstanceOf(\TypeError::class, $e->getPrevious());
        }
    }

    public function testReadObjectDoesNotWrapValueTransformerTypeError()
    {
        $reader = JsonStreamReader::create([
            StringToBooleanValueTransformer::class => new StringToBooleanValueTransformer(),
            DivideStringAndCastToIntValueTransformer::class => new DivideStringAndCastToIntValueTransformer(),
        ], $this->streamReadersDir);

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('explodeRange()');

        $reader->read('{"id":"20","active":"true","name":"name","range":["not","a","string"]}', Type::object(DummyWithValueTransformerAttributes::class), ['scale' => 1]);
    }

    public function testReadObjectWithNullableRepeatedObjects()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithNullableRepeatedDummies::class, $read);
            $this->assertSame(1, $read->first->id);
            $this->assertSame(2, $read->second->id);
        }, '{"first":{"id":1,"name":"a"},"second":{"id":2,"name":"b"}}', Type::object(DummyWithNullableRepeatedDummies::class));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertNull($read->first);
            $this->assertSame(2, $read->second->id);
        }, '{"first":null,"second":{"id":2,"name":"b"}}', Type::object(DummyWithNullableRepeatedDummies::class));
    }

    public function testReadObjectWithDateTimes()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithDateTimes::class, $read);
            $this->assertEquals(new \DateTimeImmutable('2024-11-20'), $read->interface);
            $this->assertEquals(new \DateTimeImmutable('2025-11-20'), $read->immutable);
            $this->assertEquals(new \DateTimeImmutable('2026-11-20'), $read->union);
        }, '{"interface":"2024-11-20","immutable":"2025-11-20","union":"2026-11-20"}', Type::object(DummyWithDateTimes::class));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithDateTimes::class, $read);
            $this->assertEquals(new \DateTimeImmutable('2024-11-20'), $read->interface);
            $this->assertEquals(new \DateTimeImmutable('2025-11-20'), $read->immutable);
            $this->assertEquals(10, $read->union);
        }, '{"interface":"2024-11-20","immutable":"2025-11-20","union":10}', Type::object(DummyWithDateTimes::class));
    }

    public function testReadObjectWithDateIntervals()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithDateIntervals::class, $read);
            $this->assertEquals(new \DateInterval('P2Y6M1DT12H30M5S'), $read->interval);
        }, '{"interval":"P2Y6M1DT12H30M5S"}', Type::object(DummyWithDateIntervals::class));
    }

    public function testReadObjectWithDateTimeZones()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithDateTimeZones::class, $read);
            $this->assertEquals(new \DateTimeZone('Asia/Tokyo'), $read->timezone);
        }, '{"timezone":"Asia/Tokyo"}', Type::object(DummyWithDateTimeZones::class));
    }

    #[RequiresPhpExtension('bcmath')]
    public function testReadObjectWithBcMathNumber()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithBcMathNumber::class, $read);
            $this->assertEquals(new \BcMath\Number('3.14'), $read->number);
        }, '{"number":"3.14"}', Type::object(DummyWithBcMathNumber::class));
    }

    #[RequiresPhpExtension('gmp')]
    public function testReadObjectWithGmpNumber()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithGmpNumber::class, $read);
            $this->assertEquals(new \GMP('99999999999999999999'), $read->gmp);
        }, '{"gmp":"99999999999999999999"}', Type::object(DummyWithGmpNumber::class));
    }

    public function testReadObjectWithUids()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithUids::class, $read);
            $this->assertEquals(Uuid::fromString('a7613e0a-5986-4f29-b3f8-3b6f8e5e6b40'), $read->uuid);
            $this->assertEquals(UuidV7::fromString('018f7a5e-3c1e-7a4e-8b1a-2c3d4e5f6a7b'), $read->uuidV7);
            $this->assertEquals(Ulid::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV'), $read->ulid);
        }, '{"uuid":"a7613e0a-5986-4f29-b3f8-3b6f8e5e6b40","uuidV7":"018f7a5e-3c1e-7a4e-8b1a-2c3d4e5f6a7b","ulid":"01ARZ3NDEKTSV4RRFFQ69G5FAV"}', Type::object(DummyWithUids::class));
    }

    public function testReadObjectWithUidsUsingNonCanonicalFormat()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $uuid = Uuid::fromString('a7613e0a-5986-4f29-b3f8-3b6f8e5e6b40');
        $uuidV7 = UuidV7::fromString('018f7a5e-3c1e-7a4e-8b1a-2c3d4e5f6a7b');
        $ulid = Ulid::fromString('01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertRead($reader, function (mixed $read) use ($uuid, $uuidV7, $ulid) {
            $this->assertInstanceOf(DummyWithUids::class, $read);
            $this->assertEquals($uuid, $read->uuid);
            $this->assertEquals($uuidV7, $read->uuidV7);
            $this->assertEquals($ulid, $read->ulid);
        }, \sprintf('{"uuid":"%s","uuidV7":"%s","ulid":"%s"}', $uuid->toBase58(), $uuidV7->toBase58(), $ulid->toBase58()), Type::object(DummyWithUids::class));
    }

    public function testReadUnion()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithNameAttributes::class, $read);
            $this->assertSame(10, $read->id);
            $this->assertSame('dummy', $read->name);
        }, '{"@id": 10, "name": "dummy"}', Type::union(Type::int(), Type::list(Type::enum(DummyBackedEnum::class)), Type::object(DummyWithNameAttributes::class)));

        $this->assertRead($reader, [DummyBackedEnum::ONE, DummyBackedEnum::TWO], '[1, 2]', Type::union(Type::int(), Type::list(Type::enum(DummyBackedEnum::class)), Type::object(DummyWithNameAttributes::class)));
    }

    public function testReadUnionWithRepeatedObjectListType()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $type = Type::union(Type::object(ClassicDummy::class), Type::list(Type::object(ClassicDummy::class)));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(ClassicDummy::class, $read[0]);
            $this->assertSame(10, $read[0]->id);
            $this->assertSame('dummy', $read[0]->name);
        }, '[{"id":10,"name":"dummy"}]', $type);
    }

    public function testReadUnionWithRepeatedObjectDictType()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $type = Type::union(Type::object(ClassicDummy::class), Type::dict(Type::object(ClassicDummy::class)));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(ClassicDummy::class, $read['key']);
            $this->assertSame(10, $read['key']->id);
            $this->assertSame('dummy', $read['key']->name);
        }, '{"key":{"id":10,"name":"dummy"}}', $type);
    }

    public function testReadObjectWithSyntheticProperties()
    {
        $reader = new JsonStreamReader(new Container(), new SyntheticPropertyMetadataLoader(), $this->streamReadersDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithSyntheticProperties::class, $read);
            $this->assertSame([], get_object_vars($read));
        }, '{"synthetic":true}', Type::object(DummyWithSyntheticProperties::class));
    }

    public function testCreateStreamReaderFile()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $reader->read('true', Type::bool());

        $this->assertFileExists($this->streamReadersDir);
        $this->assertCount(1, glob($this->streamReadersDir.'/*'));
    }

    public function testCreateStreamReaderFileOnlyIfNotExists()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        if (!file_exists($this->streamReadersDir)) {
            mkdir($this->streamReadersDir, recursive: true);
        }

        file_put_contents(
            \sprintf('%s%s%s.json.php', $this->streamReadersDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) Type::bool())),
            '<?php return static function () { return "CACHED"; };'
        );

        $this->assertSame('CACHED', $reader->read('true', Type::bool()));
    }

    public function testReadNestedStream()
    {
        $longText = str_repeat('A', 8192);
        $json = <<<JSON
            {
              "customProperty": "text",
              "dummies": [
                {
                  "id": 1,
                  "name": "{$longText}"
                },
                {
                  "id": 2,
                  "name": "{$longText}"
                }
              ],
              "a": ["{$longText}", "{$longText}"]
            }
            JSON;

        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->assertRead($reader, function (DummyWithIterable $read) {
            $this->assertEquals(2, iterator_count($read->dummies));
        }, $json, Type::object(DummyWithIterable::class));
    }

    public function testReadStreamCollectionItemsAtOnce()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $list = $reader->read($this->createStream('[{"id":1,"name":"a"},{"id":2,"name":"b"}]'), Type::list(Type::object(ClassicDummy::class)));

        $this->assertFalse((new \ReflectionClass(ClassicDummy::class))->isUninitializedLazyObject($list[0]));
        $this->assertSame([1, 2], array_map(static fn (ClassicDummy $d): int => $d->id, $list));

        $object = $reader->read($this->createStream('{"dummies":[{"id":1,"name":"a"}],"dummy":{"id":2,"name":"b"}}'), Type::object(DummyWithDummiesAndDummy::class));

        $this->assertTrue((new \ReflectionClass(DummyWithDummiesAndDummy::class))->isUninitializedLazyObject($object));
        $this->assertFalse((new \ReflectionClass(ClassicDummy::class))->isUninitializedLazyObject($object->dummies[0]));
        $this->assertSame(1, $object->dummies[0]->id);
        $this->assertSame(2, $object->dummy->id);

        $object = $reader->read($this->createStream('{"dummy":{"id":2,"name":"b"},"dummies":[{"id":1,"name":"a"}]}'), Type::object(DummyWithDummyAndDummies::class));

        $this->assertFalse((new \ReflectionClass(ClassicDummy::class))->isUninitializedLazyObject($object->dummies[0]));
        $this->assertSame(1, $object->dummies[0]->id);
        $this->assertSame(2, $object->dummy->id);
    }

    public function testReadStreamCollectionItemsWithoutCallingRequiredConstructor()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $list = $reader->read($this->createStream('[{"id":7}]'), Type::list(Type::object(DummyWithRequiredConstructor::class)));

        $this->assertSame(7, $list[0]->id);
        $this->assertFalse((new \ReflectionClass(DummyWithRequiredConstructor::class))->isUninitializedLazyObject($list[0]));
    }

    public function testReadStreamCollectionItemsWithoutCallingPrivateConstructor()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $list = $reader->read($this->createStream('[{"id":7}]'), Type::list(Type::object(DummyWithPrivateConstructor::class)));

        $this->assertSame(7, $list[0]->id);
        $this->assertFalse((new \ReflectionClass(DummyWithPrivateConstructor::class))->isUninitializedLazyObject($list[0]));
    }

    public function testReadStreamCollectionItemsWithoutCallingConstructor()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $list = $reader->read($this->createStream('[{"id":7}]'), Type::list(Type::object(DummyWithConstructor::class)));

        $this->assertSame(7, $list[0]->id);
        $this->assertFalse((new \ReflectionClass(DummyWithConstructor::class))->isUninitializedLazyObject($list[0]));
    }

    #[DataProvider('readStreamCollectionItemsAtOnceWithInvalidItemDataProvider')]
    public function testReadStreamCollectionItemsAtOnceWithInvalidItem(string $json, Type $type, string $message)
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage($message);

        $reader->read($this->createStream($json), $type);
    }

    /**
     * @return iterable<array{0: string, 1: Type, 2: string}>
     */
    public static function readStreamCollectionItemsAtOnceWithInvalidItemDataProvider(): iterable
    {
        yield ['[1]', Type::list(Type::object(ClassicDummy::class)), \sprintf('Unexpected "int" value for "%s".', ClassicDummy::class)];
        yield ['[[]]', Type::list(Type::object(ClassicDummy::class)), \sprintf('Unexpected "array" value for "%s".', ClassicDummy::class)];
        yield ['[{"dummy":[]}]', Type::list(Type::object(DummyWithDummyAndDummies::class)), \sprintf('Unexpected "array" value for "%s".', ClassicDummy::class)];
        yield ['[{"dummies":{"a":{}}}]', Type::list(Type::object(DummyWithDummyAndDummies::class)), \sprintf('Unexpected "stdClass" value for "list<%s>".', ClassicDummy::class)];
        yield ['[{"0":1}]', Type::list(Type::list(Type::int())), 'Unexpected "stdClass" value for "list<int>".'];
        yield ['[[1]]', Type::list(Type::dict(Type::int())), 'Unexpected "array" value for "array<string, int>".'];
        yield ['[[]]', Type::list(Type::nullable(Type::object(ClassicDummy::class))), \sprintf('Unexpected "array" value for "%s|null".', ClassicDummy::class)];
        yield ['[{"id":{"a":1}}]', Type::list(Type::object(ClassicDummy::class)), 'Unexpected "stdClass" value for "int".'];
        yield ['[[{"a":1}]]', Type::list(Type::list(Type::int())), 'Unexpected "stdClass" value for "int".'];
        yield ['[[[{"a":1}]]]', Type::list(Type::list(Type::int())), 'Unexpected "array" value for "int".'];
        yield ['[{"a":{"b":1}}]', Type::list(Type::dict(Type::union(Type::int(), Type::string()))), 'Unexpected "stdClass" value for "int|string".'];
        yield ['[null]', Type::list(Type::object(ClassicDummy::class)), \sprintf('Unexpected "null" value for "%s".', ClassicDummy::class)];
        yield ['[{"immutable":{}}]', Type::list(Type::object(DummyWithDateTimes::class)), 'Unexpected "stdClass" value for "DateTimeImmutable".'];
    }

    public function testReadStreamCollectionItemsContainingMixedValues()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $list = $reader->read($this->createStream('[{"a":{"b":[{"c":1}]}}]'), Type::list(Type::dict(Type::mixed())));

        $this->assertSame([['a' => ['b' => [['c' => 1]]]]], $list);
    }

    public function testReadStreamCollectionItemsAtOnceSetNullValues()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $list = $reader->read($this->createStream('[{"name":null},{}]'), Type::list(Type::object(DummyWithNullablePropertyWithDefault::class)));

        $this->assertNull($list[0]->name);
        $this->assertSame('default', $list[1]->name);
    }

    public function testReadStreamCollectionItemsContainingIterableLazily()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $list = $reader->read($this->createStream('[{"customProperty":"foo","dummies":[{"id":1,"name":"a"}]}]'), Type::list(Type::object(DummyWithIterable::class)));

        $this->assertTrue((new \ReflectionClass(DummyWithIterable::class))->isUninitializedLazyObject($list[0]));
        $this->assertSame('foo', $list[0]->customProperty);
        $this->assertSame(1, iterator_to_array($list[0]->dummies)[0]->id);
    }

    public function testReadStreamSelfReferencingCollectionItemsAtOnce()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $object = $reader->read($this->createStream('{"items":{"a":{"items":{"b":{"items":{}}}}}}'), Type::object(SelfReferencingDummyList::class));

        $this->assertInstanceOf(SelfReferencingDummyList::class, $object->items['a']->items['b']);
        $this->assertSame([], $object->items['a']->items['b']->items);
    }

    /**
     * @return resource
     */
    private function createStream(string $json)
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $json);
        rewind($stream);

        return $stream;
    }

    #[DataProvider('streamCollectionItemsWithNulKeysDataProvider')]
    public function testReadStreamCollectionItemsWithNulKeys(string $json, Type $type, ?string $dictKey = null)
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $items = $reader->read($this->createStream($json), $type);
        $item = null === $dictKey ? $items[0] : $items[0][$dictKey];

        $this->assertSame(3, $item->id);
    }

    public static function streamCollectionItemsWithNulKeysDataProvider(): iterable
    {
        yield ['[{"id":3,"\\u0000extra":1}]', Type::list(Type::object(ClassicDummy::class))];
        yield ['[{"id":3,"extra":{"\\u0000key":1}}]', Type::list(Type::object(ClassicDummy::class))];
        yield ['[{"\\u0000key":{"id":3}}]', Type::list(Type::dict(Type::object(ClassicDummy::class))), "\0key"];
        yield ['[{"\\u0000id":3}]', Type::list(Type::object(DummyWithNulName::class))];
        yield ['[{"id":3,"\\u0000extra":1}]', Type::list(Type::object(DummyWithRequiredConstructor::class))];
    }

    #[DataProvider('genericStreamItemCollectionsDataProvider')]
    public function testReadStreamCollectionItemsContainingGenericCollections(string $json, array $ids)
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $list = $reader->read($this->createStream($json), Type::list(Type::object(DummyWithGenericDummies::class)));

        $this->assertSame($ids, array_map(static fn (ClassicDummy $dummy): int => $dummy->id, $list[0]->dummies));
    }

    public static function genericStreamItemCollectionsDataProvider(): iterable
    {
        yield ['[{"dummies":[{"id":1},{"id":2}]}]', [1, 2]];
        yield ['[{"dummies":{"first":{"id":1},"second":{"id":2}}}]', ['first' => 1, 'second' => 2]];
    }

    #[DataProvider('duplicateStreamItemKeysDataProvider')]
    public function testReadStreamCollectionItemsRejectDuplicateKeys(string $json, Type $type)
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage('dict key twice');

        $reader->read($this->createStream($json), $type);
    }

    public static function duplicateStreamItemKeysDataProvider(): iterable
    {
        yield ['[{"id":1,"id":2}]', Type::list(Type::object(ClassicDummy::class))];
        yield ['[{"id":1,"\\u0069d":2}]', Type::list(Type::object(ClassicDummy::class))];
        yield ['[{"id":1,"name":"a:b","id":2}]', Type::list(Type::object(ClassicDummy::class))];
        yield ['{"first":{"id":1,"id":2}}', Type::dict(Type::object(ClassicDummy::class))];
        yield ['[{"dummy":{"id":1,"id":2}}]', Type::list(Type::object(DummyWithDummyAndDummies::class))];
        yield ['[{"dummies":[{"id":1,"id":2}]}]', Type::list(Type::object(DummyWithDummiesAndDummy::class))];
        yield ['[[{"id":1,"id":2}]]', Type::list(Type::list(Type::object(ClassicDummy::class)))];
        yield ['[{"left":{"id":1},"left":{"id":2}}]', Type::list(Type::dict(Type::object(ClassicDummy::class)))];
        yield ['[{"id":1,"id":2}]', Type::list(Type::nullable(Type::object(ClassicDummy::class)))];
        yield ['[{"dummies":{"first":{"id":1,"id":2}}}]', Type::list(Type::object(DummyWithGenericDummies::class))];
        yield ['[{"items":{"a":{"items":{"b":{"items":{},"items":{}}}}}}]', Type::list(Type::object(SelfReferencingDummyList::class))];
        yield ['[{"dummies":[{"id":1}],"dummies":[]}]', Type::list(Type::object(DummyWithDummiesAndDummy::class))];
        yield ['[{"dummies":{"first":{"id":1}},"dummies":[]}]', Type::list(Type::object(DummyWithGenericDummies::class))];
        yield ['[{"items":{"a":{"items":{}}},"items":{"a":{}}}]', Type::list(Type::object(SelfReferencingDummyList::class))];
    }

    public function testReadStreamCollectionItemsRejectDuplicateKeysWhenReadLazily()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage('dict key twice');

        // keys starting with a NUL byte cannot be decoded at once, so the item is read lazily
        json_encode($reader->read($this->createStream('[{"id":3,"\\u0000extra":1,"\\u0000extra":2}]'), Type::list(Type::object(ClassicDummy::class))), \JSON_THROW_ON_ERROR);
    }

    public function testReadStreamCollectionItemsWithNumericStreamedObject()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $type = Type::list(Type::object(DummyWithNumericStreamedObject::class));
        $list = $reader->read($this->createStream('[{"0":{"id":3}}]'), $type);

        $this->assertSame(3, $list[0]->dummy->id);
        $this->expectException(InvalidStreamException::class);
        $this->expectExceptionMessage('dict key twice');

        $reader->read($this->createStream('[{"0":{"id":1,"id":2}}]'), $type);
    }

    public function testReadStreamCollectionItemsAllowDuplicateKeysInUnknownValues()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);
        $list = $reader->read($this->createStream('[{"unknown":{"id":1,"id":2},"id":3}]'), Type::list(Type::object(ClassicDummy::class)));

        $this->assertSame(3, $list[0]->id);
        $this->assertFalse((new \ReflectionClass(ClassicDummy::class))->isUninitializedLazyObject($list[0]));
    }

    public function testReadStreamCollectionItemsWithDuplicateKeysInUnknownValuesRejectInvalidValues()
    {
        $reader = JsonStreamReader::create([], $this->streamReadersDir);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Cannot assign string to property');

        $reader->read($this->createStream('[{"id":"notint","unknown":{"a":1,"a":2}}]'), Type::list(Type::object(ClassicDummy::class)));
    }

    private function assertRead(JsonStreamReader $reader, mixed $readOrAssert, string $json, Type $type, array $options = []): void
    {
        $assert = \is_callable($readOrAssert, syntax_only: true) ? $readOrAssert : fn (mixed $read) => $this->assertEquals($readOrAssert, $read);

        $assert($reader->read($json, $type, $options));

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $json);
        rewind($resource);
        $assert($reader->read($resource, $type, $options));
    }
}
