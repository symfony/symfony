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
use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Exception\NotEncodableValueException;
use Symfony\Component\JsonStreamer\JsonStreamWriter;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithArray;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDollarNamedProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithGenerics;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedArray;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithPhpDoc;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUnionProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\BooleanToStringValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\DoubleIntAndCastToStringValueTransformer;
use Symfony\Component\JsonStreamer\ValueTransformer\DateTimeToStringValueTransformer;
use Symfony\Component\JsonStreamer\ValueTransformer\ValueTransformerInterface;
use Symfony\Component\TypeInfo\Type;

class JsonStreamWriterTest extends TestCase
{
    private string $streamWritersDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamWritersDir = \sprintf('%s/symfony_json_streamer_test/stream_writer', sys_get_temp_dir());

        if (is_dir($this->streamWritersDir)) {
            array_map('unlink', glob($this->streamWritersDir.'/*'));
            rmdir($this->streamWritersDir);
        }
    }

    public function testReturnTraversableAndStringable()
    {
        $writer = JsonStreamWriter::create(streamWritersDir: $this->streamWritersDir);

        $this->assertSame(['true'], iterator_to_array($writer->write(true, Type::bool())));
        $this->assertSame('true', (string) $writer->write(true, Type::bool()));
    }

    public function testWriteScalar()
    {
        $this->assertWritten('null', null, Type::null());
        $this->assertWritten('true', true, Type::bool());
        $this->assertWritten('[{"foo":1,"bar":2},{"foo":3}]', [['foo' => 1, 'bar' => 2], ['foo' => 3]], Type::list());
        $this->assertWritten('{"foo":"bar"}', (object) ['foo' => 'bar'], Type::object());
        $this->assertWritten('1', DummyBackedEnum::ONE, Type::enum(DummyBackedEnum::class));
    }

    public function testWriteUnion()
    {
        $this->assertWritten(
            '[1,true,["foo","bar"]]',
            [DummyBackedEnum::ONE, true, ['foo', 'bar']],
            Type::list(Type::union(Type::enum(DummyBackedEnum::class), Type::bool(), Type::list(Type::string()))),
        );

        $dummy = new DummyWithUnionProperties();
        $dummy->value = DummyBackedEnum::ONE;
        $this->assertWritten('{"value":1}', $dummy, Type::object(DummyWithUnionProperties::class));

        $dummy->value = 'foo';
        $this->assertWritten('{"value":"foo"}', $dummy, Type::object(DummyWithUnionProperties::class));

        $dummy->value = null;
        $this->assertWritten('{}', $dummy, Type::object(DummyWithUnionProperties::class));
    }

    public function testWriteCollection()
    {
        $this->assertWritten(
            '{"0":{"id":1,"name":"dummy"},"1":{"id":1,"name":"dummy"}}',
            [new ClassicDummy(), new ClassicDummy()],
            Type::array(Type::object(ClassicDummy::class)),
        );

        $this->assertWritten(
            '[{"id":1,"name":"dummy"},{"id":1,"name":"dummy"}]',
            [new ClassicDummy(), new ClassicDummy()],
            Type::list(Type::object(ClassicDummy::class)),
        );

        $this->assertWritten(
            '{"0":{"id":1,"name":"dummy"},"1":{"id":1,"name":"dummy"}}',
            new \ArrayObject([new ClassicDummy(), new ClassicDummy()]),
            Type::iterable(Type::object(ClassicDummy::class)),
        );

        $this->assertWritten(
            '[{"id":1,"name":"dummy"},{"id":1,"name":"dummy"}]',
            new \ArrayObject([new ClassicDummy(), new ClassicDummy()]),
            Type::iterable(Type::object(ClassicDummy::class), Type::int()),
        );
    }

    public function testWriteNestedCollection()
    {
        $dummyWithArray1 = new DummyWithArray();
        $dummyWithArray1->dummies = [new ClassicDummy()];
        $dummyWithArray1->customProperty = 'customProperty1';

        $dummyWithArray2 = new DummyWithArray();
        $dummyWithArray2->dummies = [new ClassicDummy()];
        $dummyWithArray2->customProperty = 'customProperty2';

        $this->assertWritten(
            '[{"dummies":[{"id":1,"name":"dummy"}],"customProperty":"customProperty1"},{"dummies":[{"id":1,"name":"dummy"}],"customProperty":"customProperty2"}]',
            [$dummyWithArray1, $dummyWithArray2],
            Type::list(Type::object(DummyWithArray::class)),
        );

        $dummyWithNestedArray1 = new DummyWithNestedArray();
        $dummyWithNestedArray1->dummies = [$dummyWithArray1];
        $dummyWithNestedArray1->stringProperty = 'stringProperty1';

        $dummyWithNestedArray2 = new DummyWithNestedArray();
        $dummyWithNestedArray2->dummies = [$dummyWithArray2];
        $dummyWithNestedArray2->stringProperty = 'stringProperty2';

        $this->assertWritten(
            '[{"dummies":[{"dummies":[{"id":1,"name":"dummy"}],"customProperty":"customProperty1"}],"stringProperty":"stringProperty1"},{"dummies":[{"dummies":[{"id":1,"name":"dummy"}],"customProperty":"customProperty2"}],"stringProperty":"stringProperty2"}]',
            [$dummyWithNestedArray1, $dummyWithNestedArray2],
            Type::list(Type::object(DummyWithNestedArray::class)),
        );
    }

    public function testWriteObject()
    {
        $dummy = new ClassicDummy();
        $dummy->id = 10;
        $dummy->name = 'dummy name';

        $this->assertWritten('{"id":10,"name":"dummy name"}', $dummy, Type::object(ClassicDummy::class));
    }

    public function testWriteObjectWithGenerics()
    {
        $nestedDummy = new DummyWithNameAttributes();
        $nestedDummy->id = 10;
        $nestedDummy->name = 'dummy name';

        $dummy = new DummyWithGenerics();
        $dummy->dummies = [$nestedDummy];

        $this->assertWritten('{"dummies":[{"id":10,"name":"dummy name"}]}', $dummy, Type::generic(Type::object(DummyWithGenerics::class), Type::object(ClassicDummy::class)));
    }

    public function testWriteObjectWithStreamedName()
    {
        $dummy = new DummyWithNameAttributes();
        $dummy->id = 10;
        $dummy->name = 'dummy name';

        $this->assertWritten('{"@id":10,"name":"dummy name"}', $dummy, Type::object(DummyWithNameAttributes::class));
    }

    public function testWriteObjectWithValueTransformer()
    {
        $dummy = new DummyWithValueTransformerAttributes();
        $dummy->id = 10;
        $dummy->active = true;

        $this->assertWritten(
            '{"id":"20","active":"true","name":"dummy","range":"10..20"}',
            $dummy,
            Type::object(DummyWithValueTransformerAttributes::class),
            options: ['scale' => 1],
            valueTransformers: [
                BooleanToStringValueTransformer::class => new BooleanToStringValueTransformer(),
                DoubleIntAndCastToStringValueTransformer::class => new DoubleIntAndCastToStringValueTransformer(),
            ],
        );
    }

    public function testValueTransformerHasAccessToCurrentObject()
    {
        $dummy = new DummyWithValueTransformerAttributes();
        $dummy->id = 10;
        $dummy->active = true;

        $this->assertWritten(
            '{"id":"20","active":"true","name":"dummy","range":"10..20"}',
            $dummy,
            Type::object(DummyWithValueTransformerAttributes::class),
            options: ['scale' => 1],
            valueTransformers: [
                BooleanToStringValueTransformer::class => new class($this) implements ValueTransformerInterface {
                    public function __construct(
                        private JsonStreamWriterTest $test,
                    ) {
                    }

                    public function transform(mixed $value, array $options = []): mixed
                    {
                        $this->test->assertArrayHasKey('_current_object', $options);
                        $this->test->assertInstanceof(DummyWithValueTransformerAttributes::class, $options['_current_object']);

                        return (new BooleanToStringValueTransformer())->transform($value, $options);
                    }

                    public static function getStreamValueType(): Type
                    {
                        return BooleanToStringValueTransformer::getStreamValueType();
                    }
                },
                DoubleIntAndCastToStringValueTransformer::class => new DoubleIntAndCastToStringValueTransformer(),
            ],
        );
    }

    public function testWriteObjectWithPhpDoc()
    {
        $dummy = new DummyWithPhpDoc();
        $dummy->arrayOfDummies = ['key' => new DummyWithNameAttributes()];

        $this->assertWritten('{"arrayOfDummies":{"key":{"@id":1,"name":"dummy"}},"array":[]}', $dummy, Type::object(DummyWithPhpDoc::class));
    }

    public function testWriteObjectWithNullableProperties()
    {
        $dummy = new DummyWithNullableProperties();

        $this->assertWritten('{}', $dummy, Type::object(DummyWithNullableProperties::class));

        $dummy->name = 'name';

        $this->assertWritten('{"name":"name"}', $dummy, Type::object(DummyWithNullableProperties::class));
        $this->assertWritten('{"name":"name","enum":null}', $dummy, Type::object(DummyWithNullableProperties::class), options: ['include_null_properties' => true]);

        $dummy->name = null;
        $dummy->enum = DummyBackedEnum::ONE;

        $this->assertWritten('{"enum":1}', $dummy, Type::object(DummyWithNullableProperties::class));
        $this->assertWritten('{"name":null,"enum":1}', $dummy, Type::object(DummyWithNullableProperties::class), options: ['include_null_properties' => true]);
    }

    public function testWriteObjectWithDateTimes()
    {
        $dummy = new DummyWithDateTimes();
        $dummy->interface = new \DateTimeImmutable('2024-11-20');
        $dummy->immutable = new \DateTimeImmutable('2025-11-20');

        $this->assertWritten(
            '{"interface":"2024-11-20","immutable":"2025-11-20"}',
            $dummy,
            Type::object(DummyWithDateTimes::class),
            options: [DateTimeToStringValueTransformer::FORMAT_KEY => 'Y-m-d'],
        );
    }

    public function testWriteObjectWithDollarNamedProperties()
    {
        $this->assertWritten('{"$foo":true,"{$foo->bar}":true}', new DummyWithDollarNamedProperties(), Type::object(DummyWithDollarNamedProperties::class));
    }

    #[DataProvider('throwWhenMaxDepthIsReachedDataProvider')]
    public function testThrowWhenMaxDepthIsReached(Type $type, mixed $data)
    {
        $writer = JsonStreamWriter::create(streamWritersDir: $this->streamWritersDir);

        $this->expectException(NotEncodableValueException::class);
        $this->expectExceptionMessage('Maximum stack depth exceeded');

        (string) $writer->write($data, $type);
    }

    /**
     * @return iterable<array{0: Type, 1: mixed}>
     */
    public static function throwWhenMaxDepthIsReachedDataProvider(): iterable
    {
        $dummy = new SelfReferencingDummy();
        for ($i = 0; $i < 512; ++$i) {
            $tmp = new SelfReferencingDummy();
            $tmp->self = $dummy;
            $dummy = $tmp;
        }

        yield [Type::object(SelfReferencingDummy::class), $dummy];

        $dummy = new SelfReferencingDummy();
        for ($i = 0; $i < 511; ++$i) {
            $tmp = new SelfReferencingDummy();
            $tmp->self = $dummy;
            $dummy = $tmp;
        }

        yield [Type::list(Type::object(SelfReferencingDummy::class)), [$dummy]];
        yield [Type::dict(Type::object(SelfReferencingDummy::class)), ['k' => $dummy]];
    }

    public function testThrowWhenEncodeError()
    {
        $writer = JsonStreamWriter::create(streamWritersDir: $this->streamWritersDir);

        $this->expectException(NotEncodableValueException::class);
        $this->expectExceptionMessage('Inf and NaN cannot be JSON encoded');

        (string) $writer->write(\INF, Type::int());
    }

    public function testCreateStreamWriterFile()
    {
        $writer = JsonStreamWriter::create(streamWritersDir: $this->streamWritersDir);

        $writer->write(true, Type::bool());

        $this->assertFileExists($this->streamWritersDir);
        $this->assertCount(1, glob($this->streamWritersDir.'/*'));
    }

    public function testCreateStreamWriterFileOnlyIfNotExists()
    {
        $writer = JsonStreamWriter::create(streamWritersDir: $this->streamWritersDir);

        if (!file_exists($this->streamWritersDir)) {
            mkdir($this->streamWritersDir, recursive: true);
        }

        file_put_contents(
            \sprintf('%s%s%s.json.php', $this->streamWritersDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) Type::bool())),
            '<?php return static function ($data): \Traversable { yield "CACHED"; };'
        );

        $this->assertSame('CACHED', (string) $writer->write(true, Type::bool()));
    }

    /**
     * @param array<string, mixed>                     $options
     * @param array<string, ValueTransformerInterface> $valueTransformers
     */
    private function assertWritten(string $json, mixed $data, Type $type, array $options = [], array $valueTransformers = []): void
    {
        $writer = JsonStreamWriter::create(streamWritersDir: $this->streamWritersDir, valueTransformers: $valueTransformers);
        $this->assertSame($json, (string) $writer->write($data, $type, $options));
    }
}
