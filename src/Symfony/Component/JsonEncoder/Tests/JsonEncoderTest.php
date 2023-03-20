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
use Symfony\Component\JsonEncoder\Encode\EncodeAs;
use Symfony\Component\JsonEncoder\JsonEncoder;
use Symfony\Component\JsonEncoder\Mapping\Encode\AttributePropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Mapping\PropertyMetadataLoader;
use Symfony\Component\JsonEncoder\Stream\BufferedStream;
use Symfony\Component\JsonEncoder\Stream\MemoryStream;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithAttributesUsingServices;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithFormatterAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithPhpDoc;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Contracts\Service\ServiceLocatorTrait;

class JsonEncoderTest extends TestCase
{
    private string $cacheDir;
    private JsonEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = sprintf('%s/symfony_test', sys_get_temp_dir());
        $encoderCacheDir = $this->cacheDir.'/json_encoder/encoder';

        if (is_dir($encoderCacheDir)) {
            array_map('unlink', glob($encoderCacheDir.'/*'));
            rmdir($encoderCacheDir);
        }

        $this->encoder = JsonEncoder::create($this->cacheDir);
    }

    public function testReturnTraversableStringableEncoded()
    {
        $this->assertSame(['true'], iterator_to_array($this->encoder->encode(true, Type::bool())));
        $this->assertSame('true', (string) $this->encoder->encode(true, Type::bool()));
    }

    public function testReturnEmptyWhenUsingStream()
    {
        $encoded = $this->encoder->encode(true, Type::bool(), ['stream' => $stream = new MemoryStream()]);
        $this->assertEmpty(iterator_to_array($encoded));
    }

    public function testEncodeScalar()
    {
        $this->assertEncoded('null', null, Type::null());
        $this->assertEncoded('true', true, Type::bool());
        $this->assertEncoded('[{"foo":1,"bar":2},{"foo":3}]', [['foo' => 1, 'bar' => 2], ['foo' => 3]], Type::array());
        $this->assertEncoded('{"foo":"bar"}', (object) ['foo' => 'bar'], Type::object());
        $this->assertEncoded('1', DummyBackedEnum::ONE, Type::enum(DummyBackedEnum::class));
    }

    public function testEncodeUnion()
    {
        $this->assertEncoded(
            '[1,true,["foo","bar"]]',
            [DummyBackedEnum::ONE, true, ['foo', 'bar']],
            Type::list(Type::union(Type::enum(DummyBackedEnum::class), Type::bool(), Type::list(Type::string()))),
        );

        $dummy = new DummyWithUnionProperties();
        $dummy->value = DummyBackedEnum::ONE;
        $this->assertEncoded('{"value":1}', $dummy, Type::object(DummyWithUnionProperties::class));

        $dummy->value = 'foo';
        $this->assertEncoded('{"value":"foo"}', $dummy, Type::object(DummyWithUnionProperties::class));

        $dummy->value = null;
        $this->assertEncoded('{"value":null}', $dummy, Type::object(DummyWithUnionProperties::class));
    }

    public function testEncodeObject()
    {
        $dummy = new ClassicDummy();
        $dummy->id = 10;
        $dummy->name = 'dummy name';

        $this->assertEncoded('{"id":10,"name":"dummy name"}', $dummy, Type::object(ClassicDummy::class));
    }

    public function testEncodeObjectWithEncodedName()
    {
        $dummy = new DummyWithNameAttributes();
        $dummy->id = 10;
        $dummy->name = 'dummy name';

        $this->assertEncoded('{"@id":10,"name":"dummy name"}', $dummy, Type::object(DummyWithNameAttributes::class));
    }

    public function testEncodeObjectWithEncodeFormatter()
    {
        $dummy = new DummyWithFormatterAttributes();
        $dummy->id = 10;
        $dummy->name = 'dummy name';
        $dummy->active = true;

        $this->assertEncoded('{"id":"20","name":"DUMMY NAME","active":"true"}', $dummy, Type::object(DummyWithFormatterAttributes::class));
    }

    public function testEncodeObjectWithPhpDoc()
    {
        $dummy = new DummyWithPhpDoc();
        $dummy->arrayOfDummies = ['key' => new DummyWithNameAttributes()];

        $this->assertEncoded('{"arrayOfDummies":{"key":{"@id":1,"name":"dummy"}},"array":[]}', $dummy, Type::object(DummyWithPhpDoc::class));
    }

    public function testEncodeObjectWithNullableProperties()
    {
        $dummy = new DummyWithNullableProperties();

        $this->assertEncoded('{"name":null,"enum":null}', $dummy, Type::object(DummyWithNullableProperties::class));
    }

    public function testEncodeObjectWithRuntimeServices()
    {
        $runtimeServices = new class([sprintf('%s::autowireAttribute[service]', DummyWithAttributesUsingServices::class) => fn () => fn (string $s) => strtoupper($s)]) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        $typeResolver = TypeResolver::create();
        $propertyMetadataLoader = new AttributePropertyMetadataLoader(new PropertyMetadataLoader($typeResolver), $typeResolver);
        $encoder = JsonEncoder::create($this->cacheDir, $runtimeServices);

        $dummy = new DummyWithAttributesUsingServices();

        $this->assertSame('{"one":"one","two":"USELESS","three":"three"}', (string) $encoder->encode($dummy, Type::object(DummyWithAttributesUsingServices::class)));

        $encoder->encode($dummy, Type::object(DummyWithAttributesUsingServices::class), ['stream' => $stream = new MemoryStream()]);
        $stream->rewind();
        $this->assertSame('{"one":"one","two":"USELESS","three":"three"}', $stream->read());
    }

    public function testCreateCacheFile()
    {
        $encoderCacheDir = $this->cacheDir.'/json_encoder/encoder';

        $this->encoder->encode(true, Type::bool());

        $this->assertFileExists($encoderCacheDir);
        $this->assertCount(1, glob($encoderCacheDir.'/*'));
    }

    public function testCreateCacheFileOnlyIfNotExists()
    {
        $encoderCacheDir = $this->cacheDir.'/json_encoder/encoder';

        if (!file_exists($encoderCacheDir)) {
            mkdir($encoderCacheDir, recursive: true);
        }

        file_put_contents(
            sprintf('%s%s%s.json.%s.php', $encoderCacheDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) Type::bool()), EncodeAs::STRING->value),
            '<?php return static function ($data): \Traversable { yield "CACHED"; };'
        );

        $this->assertSame('CACHED', (string) $this->encoder->encode(true, Type::bool()));
    }

    public function testRecreateCacheFileIfForceGeneration()
    {
        $encoderCacheDir = $this->cacheDir.'/json_encoder/encoder';

        if (!file_exists($encoderCacheDir)) {
            mkdir($encoderCacheDir, recursive: true);
        }

        file_put_contents(
            sprintf('%s%s%s.json.%s.php', $encoderCacheDir, \DIRECTORY_SEPARATOR, hash('xxh128', (string) Type::bool()), EncodeAs::STRING->value),
            '<?php return static function ($data): \Traversable { yield "CACHED"; };'
        );

        $this->assertSame('true', (string) $this->encoder->encode(true, Type::bool(), ['force_generation' => true]));
    }

    private function assertEncoded(string $encoded, mixed $decoded, Type $type): void
    {
        $this->assertSame($encoded, (string) $this->encoder->encode($decoded, $type));

        $this->encoder->encode($decoded, $type, ['stream' => $stream = new MemoryStream()]);
        $stream->rewind();
        $this->assertSame($encoded, (string) $stream);

        $this->encoder->encode($decoded, $type, ['stream' => $stream = new BufferedStream()]);
        $stream->rewind();
        $this->assertSame($encoded, (string) $stream);
    }
}
