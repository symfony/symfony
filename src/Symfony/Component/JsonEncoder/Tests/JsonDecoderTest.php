<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\JsonDecoder;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\BooleanStringDenormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\DivideStringAndCastToIntDenormalizer;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithDateTimes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithPhpDoc;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;

class JsonDecoderTest extends TestCase
{
    private string $decodersDir;
    private string $lazyGhostsDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->decodersDir = \sprintf('%s/symfony_json_encoder_test/decoder', sys_get_temp_dir());
        $this->lazyGhostsDir = \sprintf('%s/symfony_json_encoder_test/lazy_ghost', sys_get_temp_dir());

        if (is_dir($this->decodersDir)) {
            array_map('unlink', glob($this->decodersDir.'/*'));
            rmdir($this->decodersDir);
        }

        if (is_dir($this->lazyGhostsDir)) {
            array_map('unlink', glob($this->lazyGhostsDir.'/*'));
            rmdir($this->lazyGhostsDir);
        }
    }

    public function testDecodeScalar()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertDecoded($decoder, null, 'null', Type::nullable(Type::int()));
        $this->assertDecoded($decoder, true, 'true', Type::bool());
        $this->assertDecoded($decoder, [['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::builtin(TypeIdentifier::ARRAY));
        $this->assertDecoded($decoder, [['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::builtin(TypeIdentifier::ITERABLE));
        $this->assertDecoded($decoder, (object) ['foo' => 'bar'], '{"foo": "bar"}', Type::object());
        $this->assertDecoded($decoder, DummyBackedEnum::ONE, '1', Type::enum(DummyBackedEnum::class, Type::string()));
    }

    public function testDecodeCollection()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertDecoded(
            $decoder,
            [true, false],
            '{"0": true, "1": false}',
            Type::array(Type::bool()),
        );

        $this->assertDecoded(
            $decoder,
            [true, false],
            '[true, false]',
            Type::list(Type::bool()),
        );

        $this->assertDecoded($decoder, function (mixed $decoded) {
            $this->assertIsIterable($decoded);
            $this->assertSame([true, false], iterator_to_array($decoded));
        }, '{"0": true, "1": false}', Type::iterable(Type::bool()));

        $this->assertDecoded($decoder, function (mixed $decoded) {
            $this->assertIsIterable($decoded);
            $this->assertSame([true, false], iterator_to_array($decoded));
        }, '{"0": true, "1": false}', Type::iterable(Type::bool(), Type::int()));
    }

    public function testDecodeObject()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertDecoded($decoder, function (mixed $decoded) {
            $this->assertInstanceOf(ClassicDummy::class, $decoded);
            $this->assertSame(10, $decoded->id);
            $this->assertSame('dummy name', $decoded->name);
        }, '{"id": 10, "name": "dummy name"}', Type::object(ClassicDummy::class));
    }

    public function testDecodeObjectWithEncodedName()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertDecoded($decoder, function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithNameAttributes::class, $decoded);
            $this->assertSame(10, $decoded->id);
        }, '{"@id": 10}', Type::object(DummyWithNameAttributes::class));
    }

    public function testDecodeObjectWithDenormalizer()
    {
        $decoder = JsonDecoder::create(
            denormalizers: [
                BooleanStringDenormalizer::class => new BooleanStringDenormalizer(),
                DivideStringAndCastToIntDenormalizer::class => new DivideStringAndCastToIntDenormalizer(),
            ],
            decodersDir: $this->decodersDir,
            lazyGhostsDir: $this->lazyGhostsDir,
        );

        $this->assertDecoded($decoder, function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithNormalizerAttributes::class, $decoded);
            $this->assertSame(10, $decoded->id);
            $this->assertTrue($decoded->active);
            $this->assertSame('LOWERCASE NAME', $decoded->name);
            $this->assertSame([0, 1], $decoded->range);
        }, '{"id": "20", "active": "true", "name": "lowercase name", "range": "0..1"}', Type::object(DummyWithNormalizerAttributes::class), ['scale' => 1]);
    }

    public function testDecodeObjectWithPhpDoc()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertDecoded($decoder, function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithPhpDoc::class, $decoded);
            $this->assertIsArray($decoded->arrayOfDummies);
            $this->assertContainsOnlyInstancesOf(DummyWithNameAttributes::class, $decoded->arrayOfDummies);
            $this->assertArrayHasKey('key', $decoded->arrayOfDummies);
        }, '{"arrayOfDummies":{"key":{"@id":10,"name":"dummy"}}}', Type::object(DummyWithPhpDoc::class));
    }

    public function testDecodeObjectWithNullableProperties()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertDecoded($decoder, function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithNullableProperties::class, $decoded);
            $this->assertNull($decoded->name);
            $this->assertNull($decoded->enum);
        }, '{"name":null,"enum":null}', Type::object(DummyWithNullableProperties::class));
    }

    public function testDecodeObjectWithDateTimes()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $this->assertDecoded($decoder, function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithDateTimes::class, $decoded);
            $this->assertEquals(new \DateTimeImmutable('2024-11-20'), $decoded->interface);
            $this->assertEquals(new \DateTimeImmutable('2025-11-20'), $decoded->immutable);
            $this->assertEquals(new \DateTime('2024-10-05'), $decoded->mutable);
        }, '{"interface":"2024-11-20","immutable":"2025-11-20","mutable":"2024-10-05"}', Type::object(DummyWithDateTimes::class));
    }

    public function testCreateDecoderFile()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        $decoder->decode('true', Type::bool());

        $this->assertFileExists($this->decodersDir);
        $this->assertCount(1, glob($this->decodersDir.'/*'));
    }

    public function testCreateDecoderFileOnlyIfNotExists()
    {
        $decoder = JsonDecoder::create(decodersDir: $this->decodersDir, lazyGhostsDir: $this->lazyGhostsDir);

        if (!file_exists($this->decodersDir)) {
            mkdir($this->decodersDir, recursive: true);
        }

        file_put_contents(
            \sprintf('%s%s%s.json.php', $this->decodersDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) Type::bool())),
            '<?php return static function () { return "CACHED"; };'
        );

        $this->assertSame('CACHED', $decoder->decode('true', Type::bool()));
    }

    private function assertDecoded(JsonDecoder $decoder, mixed $decodedOrAssert, string $encoded, Type $type, array $options = []): void
    {
        $assert = \is_callable($decodedOrAssert, syntax_only: true) ? $decodedOrAssert : fn (mixed $decoded) => $this->assertEquals($decodedOrAssert, $decoded);

        $assert($decoder->decode($encoded, $type, $options));

        $resource = fopen('php://temp', 'w');
        fwrite($resource, $encoded);
        rewind($resource);
        $assert($decoder->decode($resource, $type, $options));
    }
}
