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

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\JsonStreamReader;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithPhpDoc;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\DivideStringAndCastToIntValueTransformer;
use Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\StringToBooleanValueTransformer;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

class JsonStreamReaderTest extends TestCase
{
    private string $streamReadersDir;
    private string $lazyGhostsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->streamReadersDir = \sprintf('%s/symfony_json_streamer_test/stream_reader', sys_get_temp_dir());
        $this->lazyGhostsDir = \sprintf('%s/symfony_json_streamer_test/lazy_ghost', sys_get_temp_dir());

        if (is_dir($this->streamReadersDir)) {
            array_map('unlink', glob($this->streamReadersDir.'/*'));
            rmdir($this->streamReadersDir);
        }

        if (is_dir($this->lazyGhostsDir)) {
            array_map('unlink', glob($this->lazyGhostsDir.'/*'));
            rmdir($this->lazyGhostsDir);
        }
    }

    public function testReadScalar()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertRead($reader, null, 'null', Type::nullable(Type::int()));
        $this->assertRead($reader, true, 'true', Type::bool());
        $this->assertRead($reader, [['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::builtin(TypeIdentifier::ARRAY));
        $this->assertRead($reader, [['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::builtin(TypeIdentifier::ITERABLE));
        $this->assertRead($reader, (object) ['foo' => 'bar'], '{"foo": "bar"}', Type::object());
        $this->assertRead($reader, DummyBackedEnum::ONE, '1', Type::enum(DummyBackedEnum::class, Type::string()));
    }

    public function testReadCollection()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

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
            Type::list(Type::bool()),
        );

        $this->assertRead($reader, function (mixed $read) {
            $this->assertIsIterable($read);
            $this->assertSame([true, false], iterator_to_array($read));
        }, '{"0": true, "1": false}', Type::iterable(Type::bool()));

        $this->assertRead($reader, function (mixed $read) {
            $this->assertIsIterable($read);
            $this->assertSame([true, false], iterator_to_array($read));
        }, '{"0": true, "1": false}', Type::iterable(Type::bool(), Type::int()));
    }

    public function testReadObject()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(ClassicDummy::class, $read);
            $this->assertSame(10, $read->id);
            $this->assertSame('dummy name', $read->name);
        }, '{"id": 10, "name": "dummy name"}', Type::object(ClassicDummy::class));
    }

    public function testReadObjectWithStreamedName()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithNameAttributes::class, $read);
            $this->assertSame(10, $read->id);
        }, '{"@id": 10}', Type::object(DummyWithNameAttributes::class));
    }

    public function testReadObjectWithValueTransformer()
    {
        $reader = JsonStreamReader::create(
            valueTransformers: [
                StringToBooleanValueTransformer::class => new StringToBooleanValueTransformer(),
                DivideStringAndCastToIntValueTransformer::class => new DivideStringAndCastToIntValueTransformer(),
            ],
            streamReadersDir: $this->streamReadersDir,
            lazyGhostsDir: $this->lazyGhostsDir,
        );

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithValueTransformerAttributes::class, $read);
            $this->assertSame(10, $read->id);
            $this->assertTrue($read->active);
            $this->assertSame('LOWERCASE NAME', $read->name);
            $this->assertSame([0, 1], $read->range);
        }, '{"id": "20", "active": "true", "name": "lowercase name", "range": "0..1"}', Type::object(DummyWithValueTransformerAttributes::class), ['scale' => 1]);
    }

    public function testReadObjectWithPhpDoc()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithPhpDoc::class, $read);
            $this->assertIsArray($read->arrayOfDummies);
            $this->assertContainsOnlyInstancesOf(DummyWithNameAttributes::class, $read->arrayOfDummies);
            $this->assertArrayHasKey('key', $read->arrayOfDummies);
        }, '{"arrayOfDummies":{"key":{"@id":10,"name":"dummy"}}}', Type::object(DummyWithPhpDoc::class));
    }

    public function testReadObjectWithNullableProperties()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithNullableProperties::class, $read);
            $this->assertNull($read->name);
            $this->assertNull($read->enum);
        }, '{"name":null,"enum":null}', Type::object(DummyWithNullableProperties::class));
    }

    public function testReadObjectWithDateTimes()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertRead($reader, function (mixed $read) {
            $this->assertInstanceOf(DummyWithDateTimes::class, $read);
            $this->assertEquals(new \DateTimeImmutable('2024-11-20'), $read->interface);
            $this->assertEquals(new \DateTimeImmutable('2025-11-20'), $read->immutable);
        }, '{"interface":"2024-11-20","immutable":"2025-11-20"}', Type::object(DummyWithDateTimes::class));
    }

    public function testCreateStreamReaderFile()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $reader->read('true', Type::bool());

        $this->assertFileExists($this->streamReadersDir);
        $this->assertCount(1, glob($this->streamReadersDir.'/*'));
    }

    public function testCreateStreamReaderFileOnlyIfNotExists()
    {
        $reader = JsonStreamReader::create(streamReadersDir: $this->streamReadersDir, lazyGhostsDir: $this->lazyGhostsDir);

        if (!file_exists($this->streamReadersDir)) {
            mkdir($this->streamReadersDir, recursive: true);
        }

        file_put_contents(
            \sprintf('%s%s%s.json.php', $this->streamReadersDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) Type::bool())),
            '<?php return static function () { return "CACHED"; };'
        );

        $this->assertSame('CACHED', $reader->read('true', Type::bool()));
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
