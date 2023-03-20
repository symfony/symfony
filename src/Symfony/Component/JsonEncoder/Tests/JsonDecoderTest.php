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
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\Decode\DecodeFrom;
use Symfony\Component\JsonEncoder\JsonDecoder;
use Symfony\Component\JsonEncoder\Stream\MemoryStream;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithAttributesUsingServices;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithPhpDoc;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class JsonDecoderTest extends TestCase
{
    private string $cacheDir;
    private JsonDecoder $decoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sprintf('%s/symfony_test', sys_get_temp_dir());

        $decoderCacheDir = $this->cacheDir.'/json_encoder/decoder';
        $lazyGhostCacheDir = $this->cacheDir.'/json_encoder/lazy_ghost';

        if (is_dir($decoderCacheDir)) {
            array_map('unlink', glob($decoderCacheDir.'/*'));
            rmdir($decoderCacheDir);
        }

        if (is_dir($lazyGhostCacheDir)) {
            array_map('unlink', glob($lazyGhostCacheDir.'/*'));
            rmdir($lazyGhostCacheDir);
        }

        $this->decoder = JsonDecoder::create($this->cacheDir);
    }

    public function testDecodeScalar()
    {
        $this->assertDecoded(null, 'null', Type::nullable(Type::int()));
        $this->assertDecoded(true, 'true', Type::bool());
        $this->assertDecoded([['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::builtin(TypeIdentifier::ARRAY));
        $this->assertDecoded([['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::builtin(TypeIdentifier::ITERABLE));
        $this->assertDecoded((object) ['foo' => 'bar'], '{"foo": "bar"}', Type::object());
        $this->assertDecoded(DummyBackedEnum::ONE, '1', Type::enum(DummyBackedEnum::class, Type::string()));
    }

    public function testDecodeCollection()
    {
        $this->assertDecoded([['foo' => 1, 'bar' => 2], ['foo' => 3]], '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::list(Type::dict(Type::int())));
        $this->assertDecoded(function (mixed $decoded) {
            $this->assertIsIterable($decoded);
            $array = [];
            foreach ($decoded as $item) {
                $array[] = iterator_to_array($item);
            }

            $this->assertSame([['foo' => 1, 'bar' => 2], ['foo' => 3]], $array);
        }, '[{"foo": 1, "bar": 2}, {"foo": 3}]', Type::iterable(Type::iterable(Type::int()), Type::int(), asList: true));
    }

    public function testDecodeObject()
    {
        $this->assertDecoded(function (mixed $decoded) {
            $this->assertInstanceOf(ClassicDummy::class, $decoded);
            $this->assertSame(10, $decoded->id);
            $this->assertSame('dummy name', $decoded->name);
        }, '{"id": 10, "name": "dummy name"}', Type::object(ClassicDummy::class));
    }

    public function testDecodeObjectWithEncodedName()
    {
        $this->assertDecoded(function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithNameAttributes::class, $decoded);
            $this->assertSame(10, $decoded->id);
        }, '{"@id": 10}', Type::object(DummyWithNameAttributes::class));
    }

    public function testDecodeObjectWithDecodeFormatter()
    {
        $this->assertDecoded(function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithFormatterAttributes::class, $decoded);
            $this->assertSame(10, $decoded->id);
            $this->assertSame('dummy name', $decoded->name);
            $this->assertTrue($decoded->active);
        }, '{"id": "20", "name": "DUMMY NAME", "active": "true"}', Type::object(DummyWithFormatterAttributes::class));
    }

    public function testDecodeObjectWithPhpDoc()
    {
        $this->assertDecoded(function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithPhpDoc::class, $decoded);
            $this->assertIsArray($decoded->arrayOfDummies);
            $this->assertContainsOnlyInstancesOf(DummyWithNameAttributes::class, $decoded->arrayOfDummies);
            $this->assertArrayHasKey('key', $decoded->arrayOfDummies);
        }, '{"arrayOfDummies":{"key":{"@id":10,"name":"dummy"}}}', Type::object(DummyWithPhpDoc::class));
    }

    public function testDecodeObjectWithNullableProperties()
    {
        $this->assertDecoded(function (mixed $decoded) {
            $this->assertInstanceOf(DummyWithNullableProperties::class, $decoded);
            $this->assertNull($decoded->name);
            $this->assertNull($decoded->enum);
        }, '{"name":null,"enum":null}', Type::object(DummyWithNullableProperties::class));
    }

    public function testDecodeObjectWithRuntimeServices()
    {
        $service = JsonDecoder::create();

        $runtimeServices = new class([sprintf('%s::serviceAndConfig[service]', DummyWithAttributesUsingServices::class) => fn () => $service]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        $decoder = JsonDecoder::create($this->cacheDir, $runtimeServices);

        $encoded = '{"one":"\"one\"","two":"two","three":"three"}';

        $decoded = $decoder->decode($encoded, Type::object(DummyWithAttributesUsingServices::class));
        $this->assertInstanceOf(DummyWithAttributesUsingServices::class, $decoded);
        $this->assertSame('one', $decoded->one);

        $traversable = new \ArrayIterator(str_split($encoded, 2));
        $this->assertInstanceOf(DummyWithAttributesUsingServices::class, $decoded);
        $this->assertSame('one', $decoded->one);

        $stream = new MemoryStream();
        $stream->write($encoded);
        $stream->rewind();
        $this->assertInstanceOf(DummyWithAttributesUsingServices::class, $decoded);
        $this->assertSame('one', $decoded->one);
    }

    public function testCreateCacheFile()
    {
        $this->decoder->decode('true', Type::bool());

        $decoderCacheDir = $this->cacheDir.'/json_encoder/decoder';

        $this->assertFileExists($decoderCacheDir);
        $this->assertCount(1, glob($decoderCacheDir.'/*'));
    }

    public function testCreateCacheFileOnlyIfNotExists()
    {
        $decoderCacheDir = $this->cacheDir.'/json_encoder/decoder';

        if (!file_exists($decoderCacheDir)) {
            mkdir($decoderCacheDir, recursive: true);
        }

        file_put_contents(
            sprintf('%s%s%s.json.%s.php', $decoderCacheDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) Type::bool()), DecodeFrom::STRING->value),
            '<?php return static function () { return "CACHED"; };'
        );

        $this->assertSame('CACHED', $this->decoder->decode('true', Type::bool()));
    }

    public function testRecreateCacheFileIfForceGeneration()
    {
        $decoderCacheDir = $this->cacheDir.'/json_encoder/decoder';

        if (!file_exists($decoderCacheDir)) {
            mkdir($decoderCacheDir, recursive: true);
        }

        file_put_contents(
            sprintf('%s%s%s.json.%s.php', $decoderCacheDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) Type::bool()), DecodeFrom::STRING->value),
            '<?php return static function () { return "CACHED"; };'
        );

        $this->assertTrue($this->decoder->decode('true', Type::bool(), ['force_generation' => true]));
    }

    private function decode(string $input, Type $type, JsonDecoder $decoder, array $config = []): mixed
    {
        if ($decoder instanceof JsonDecoder) {
            return $decoder->decode($input, $type, $config);
        }

        $inputStream = (new MemoryStream());
        fwrite($inputStream->getResource(), $input);
        rewind($inputStream->getResource());

        return $decoder->decode($inputStream, $type, $config);
    }

    private function assertDecoded(mixed $decodedOrAssert, string $encoded, Type $type): void
    {
        $assert = \is_callable($decodedOrAssert, syntax_only: true) ? $decodedOrAssert : fn (mixed $decoded) => $this->assertEquals($decodedOrAssert, $decoded);

        $assert($this->decoder->decode($encoded, $type));

        $stringable = new class($encoded) implements \Stringable {
            public function __construct(private string $string)
            {
            }

            public function __toString(): string
            {
                return $this->string;
            }
        };
        $assert($this->decoder->decode($stringable, $type));

        $traversable = new \ArrayIterator(str_split($encoded, 2));
        $assert($this->decoder->decode($traversable, $type));

        $stream = new MemoryStream();
        $stream->write($encoded);
        $stream->rewind();
        $assert($this->decoder->decode($stream, $type));
    }
}
