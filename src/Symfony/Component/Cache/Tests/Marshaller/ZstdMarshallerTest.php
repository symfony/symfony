<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Tests\Marshaller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Exception\InvalidArgumentException;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\DeflateMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\Marshaller\ZstdMarshaller;
use Symfony\Component\Filesystem\Filesystem;

#[RequiresPhpExtension('zstd')]
class ZstdMarshallerTest extends TestCase
{
    private const MAGIC = "\x28\xB5\x2F\xFD";

    /**
     * A skippable frame: a magic number from 0x184D2A50 to 0x184D2A5F, then
     * the length of the bytes a decoder skips (RFC 8878, 3.1.2).
     */
    private const SKIPPABLE = "\x50\x2A\x4D\x18\x04\x00\x00\x00skip";

    public function testSmallValuesAreNotCompressed()
    {
        $inner = new DefaultMarshaller();
        $value = str_repeat('a', 200);

        $result = (new ZstdMarshaller($inner))->marshall(['k' => $value], $failed);

        $this->assertSame($inner->marshall(['k' => $value], $ignored), $result, 'compressible, but shorter than the threshold');
        $this->assertSame([], $failed);
    }

    public function testLargeValuesAreCompressed()
    {
        $marshaller = new ZstdMarshaller(new DefaultMarshaller());
        $model = self::model();

        $stored = self::stored($marshaller, $model);

        $this->assertStringStartsWith(self::MAGIC, $stored);
        $this->assertLessThan(\strlen(serialize($model)), \strlen($stored));
        $this->assertSame($model, $marshaller->unmarshall($stored));
    }

    public function testIncompressibleValuesAreNotCompressed()
    {
        // hash digests do not compress: the frame is longer than the value
        $binary = implode('', array_map(static fn (int $i) => hash('sha512', 'cache value '.$i, true), range(1, 64)));

        $result = (new ZstdMarshaller(self::passThrough(), threshold: 0))->marshall(['k' => $binary], $failed);

        $this->assertSame(['k' => $binary], $result);
    }

    public function testValuesStartingWithMagicNumberAreAlwaysCompressed()
    {
        $marshaller = new ZstdMarshaller(self::passThrough());
        $lookalike = self::MAGIC.'tiny';

        $stored = self::stored($marshaller, $lookalike, $failed);

        $this->assertNotSame($lookalike, $stored, 'stored raw, it would be read as a frame');
        $this->assertSame($lookalike, $marshaller->unmarshall($stored));
        $this->assertSame([], $failed);
    }

    public function testUnmarshallUncompressedValue()
    {
        $inner = new DefaultMarshaller();
        $model = self::model();

        $this->assertSame($model, (new ZstdMarshaller($inner))->unmarshall(self::stored($inner, $model)));
    }

    public function testUnmarshallForwardsDeflatedValue()
    {
        $deflated = self::stored(new DeflateMarshaller(new DefaultMarshaller()), self::model());
        $inner = self::passThrough();

        (new ZstdMarshaller($inner))->unmarshall($deflated);

        $this->assertSame([$deflated], $inner->read, 'no header to recognise: the decorated marshaller refuses it, a miss');
    }

    public function testUnmarshallThrowsOnCorruptFrame()
    {
        // a decorated marshaller that accepts anything: the refusal is this one's
        $marshaller = new ZstdMarshaller(self::passThrough());
        $stored = self::stored($marshaller, serialize(self::model()));

        // the header kept, the blocks after it overwritten
        $damaged = substr($stored, 0, 12).str_repeat("\xFF", \strlen($stored) - 12);

        $this->expectException(\DomainException::class);

        $marshaller->unmarshall($damaged);
    }

    #[DataProvider('provideTruncatedFrameValues')]
    public function testUnmarshallThrowsOnTruncatedFrame(string $value)
    {
        $marshaller = new ZstdMarshaller(self::passThrough());
        $stored = self::stored($marshaller, $value);

        $this->expectException(\DomainException::class);

        $marshaller->unmarshall(substr($stored, 0, intdiv(\strlen($stored), 2)));
    }

    public static function provideTruncatedFrameValues(): iterable
    {
        yield 'one block' => [serialize(self::model())];

        // past 128 KiB a frame has several blocks, and a cut one inflates to the blocks before the cut
        yield 'many blocks' => [self::text(400000)];
    }

    public function testUnmarshallThrowsOnFrameAboveMaxLength()
    {
        // a whole frame of a 2 KiB value, which would inflate without fault
        $frame = zstd_compress(self::text(2048));
        $this->assertIsString($frame);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('states no content size or one out of bounds');

        (new ZstdMarshaller(self::passThrough(), maxLength: 1024))->unmarshall($frame);
    }

    public function testUnmarshallThrowsOnFrameWithoutContentSize()
    {
        if (!\function_exists('zstd_compress_init')) {
            $this->markTestSkipped('ext-zstd has no streaming calls.');
        }

        // the streaming encoder starts a frame before it knows the size, and states none
        $context = zstd_compress_init();
        $this->assertNotFalse($context);
        $frame = zstd_compress_add($context, self::text(2048), false).zstd_compress_add($context, '', true);
        $this->assertSame(0, \ord($frame[4]) & 0xE0, 'the header holds no content size');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('states no content size or one out of bounds');

        (new ZstdMarshaller(self::passThrough()))->unmarshall($frame);
    }

    public function testUnmarshallThrowsOnEmptyFrame()
    {
        // marshall() never writes one: no frame is shorter than an empty value
        $frame = zstd_compress('');
        $this->assertIsString($frame);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('states no content size or one out of bounds');

        (new ZstdMarshaller(self::passThrough()))->unmarshall($frame);
    }

    public function testUnmarshallThrowsOnReservedBit()
    {
        $marshaller = new ZstdMarshaller(self::passThrough());
        $stored = self::stored($marshaller, self::text(5000));
        $reserved = substr($stored, 0, 4).\chr(\ord($stored[4]) | 0x08).substr($stored, 5);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('sets the reserved bit');

        $marshaller->unmarshall($reserved);
    }

    #[DataProvider('provideValuesNotExactlyOneFrame')]
    public function testUnmarshallThrowsOnValueNotExactlyOneFrame(\Closure $extend)
    {
        $marshaller = new ZstdMarshaller(self::passThrough());
        $stored = self::stored($marshaller, self::text(5000));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not exactly one whole frame');

        $marshaller->unmarshall($extend($stored));
    }

    public static function provideValuesNotExactlyOneFrame(): iterable
    {
        yield 'bytes after the frame' => [static fn (string $frame) => $frame.'garbage'];
        yield 'two frames' => [static fn (string $frame) => $frame.$frame];
        yield 'skippable frame after the frame' => [static fn (string $frame) => $frame.self::SKIPPABLE];
    }

    public function testUnmarshallFrameWithChecksum()
    {
        // zstd_compress() writes no checksum, other encoders do: the low four
        // bytes of the value's XXH64, little-endian, after the last block
        $value = self::text(1000);
        $frame = self::MAGIC."\xA4".pack('V', \strlen($value)) // a four-byte size, a single segment, a checksum
            .substr(pack('V', \strlen($value) << 3 | 1), 0, 3).$value // one raw block, the last
            .strrev(substr(hash('xxh64', $value, true), 4));

        $this->assertSame($value, zstd_uncompress($frame), 'the extension reads it');
        $this->assertSame($value, (new ZstdMarshaller(self::passThrough()))->unmarshall($frame));
    }

    public function testValuesStartingWithSkippableFrameAreNotInflated()
    {
        // only the zstd magic number marks a frame, so the frame behind is not inflated
        $inner = self::passThrough();
        $value = self::SKIPPABLE.self::stored(new ZstdMarshaller($inner), self::text(5000));

        $this->assertSame($value, (new ZstdMarshaller($inner))->unmarshall($value));
        $this->assertSame([$value], $inner->read);
    }

    public function testValuesAboveMaxLengthAreNotCompressed()
    {
        $long = self::text(5000);

        $result = (new ZstdMarshaller(self::passThrough(), maxLength: 4096))->marshall(['k' => $long], $failed);

        $this->assertSame(['k' => $long], $result);
        $this->assertSame([], $failed);
    }

    public function testValuesStartingWithMagicNumberAboveMaxLengthFail()
    {
        $result = (new ZstdMarshaller(self::passThrough(), maxLength: 4096))->marshall(['k' => self::MAGIC.self::text(5000)], $failed);

        $this->assertSame([], $result, 'stored raw, it would be read as a frame; compressed, it would be refused');
        $this->assertSame(['k'], $failed);
    }

    public function testFailedValues()
    {
        $result = (new ZstdMarshaller(new DefaultMarshaller()))->marshall(['failed' => static fn () => null, 'good' => self::model()], $failed);

        $this->assertSame(['failed'], $failed);
        $this->assertSame(['good'], array_keys($result));
    }

    #[DataProvider('provideInvalidArguments')]
    public function testInvalidArgument(array $arguments, string $message)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new ZstdMarshaller(new DefaultMarshaller(), ...$arguments);
    }

    public static function provideInvalidArguments(): iterable
    {
        yield 'compression level below minimum' => [['compressionLevel' => 0], 'The compression level must be between 1 and 22, "0" given.'];
        yield 'compression level above maximum' => [['compressionLevel' => 23], 'The compression level must be between 1 and 22, "23" given.'];
        yield 'negative threshold' => [['threshold' => -1], 'The threshold must not be negative, "-1" given.'];
        yield 'maximum length below one' => [['maxLength' => 0], 'The maximum length must be positive, "0" given.'];
    }

    public function testFilesystemAdapter()
    {
        $directory = sys_get_temp_dir().'/symfony-zstd-marshaller-'.bin2hex(random_bytes(4));

        try {
            $pool = new FilesystemAdapter('', 0, $directory, new ZstdMarshaller(new DefaultMarshaller()));
            $model = self::model();

            $this->assertTrue($pool->save($pool->getItem('model')->set($model)));

            $files = iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)), false);
            $this->assertCount(1, $files);
            $this->assertStringContainsString(self::MAGIC, file_get_contents($files[0]->getPathname()), 'the file holds the frame');

            $read = (new FilesystemAdapter('', 0, $directory, new ZstdMarshaller(new DefaultMarshaller())))->getItem('model');
            $this->assertTrue($read->isHit());
            $this->assertSame($model, $read->get());
        } finally {
            (new Filesystem())->remove($directory);
        }
    }

    /**
     * What $marshaller stores for $value.
     */
    private static function stored(MarshallerInterface $marshaller, mixed $value, ?array &$failed = null): string
    {
        $stored = $marshaller->marshall(['k' => $value], $failed)['k'] ?? null;
        self::assertIsString($stored);

        return $stored;
    }

    /**
     * A marshaller that writes strings as they are and remembers what it was asked to read.
     */
    private static function passThrough(): MarshallerInterface
    {
        return new class implements MarshallerInterface {
            public array $read = [];

            public function marshall(array $values, ?array &$failed): array
            {
                $failed = [];

                return array_map(static fn (mixed $value): string => \is_string($value) ? $value : serialize($value), $values);
            }

            public function unmarshall(string $value): mixed
            {
                $this->read[] = $value;

                return $value;
            }
        };
    }

    /**
     * $length bytes of varied words, which compress but not to nothing.
     */
    private static function text(int $length): string
    {
        $text = '';

        for ($i = 0; \strlen($text) < $length; ++$i) {
            $text .= 'word'.(crc32((string) $i) % 10000).' ';
        }

        return substr($text, 0, $length);
    }

    /**
     * A product model of about 2.4 KB once serialized: text, URLs, identifiers.
     */
    private static function model(): array
    {
        $variants = [];

        for ($i = 0; $i < 12; ++$i) {
            $variants[] = ['sku' => \sprintf('SKU-%05d-%02d', 4711, $i), 'price' => 1999 + 100 * $i, 'url' => 'https://shop.example/products/4711/variant-'.$i, 'stock' => $i % 3];
        }

        return [
            'id' => 4711,
            'name' => 'Lightweight waterproof hiking jacket',
            'description' => str_repeat('A breathable shell for wet days on the trail, with taped seams and a hood that fits over a helmet. ', 6),
            'variants' => $variants,
        ];
    }
}
