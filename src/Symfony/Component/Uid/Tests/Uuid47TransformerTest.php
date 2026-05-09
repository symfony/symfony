<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid47Transformer;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV7;

#[RequiresPhpExtension('sodium')]
class Uuid47TransformerTest extends TestCase
{
    private function createConverter(): Uuid47Transformer
    {
        // K0 = 0x0123456789abcdef, K1 = 0xfedcba9876543210 (little-endian byte representation)
        return new Uuid47Transformer(hex2bin('efcdab89674523011032547698badcfe'));
    }

    public function testConstructorRequiresAtLeast16ByteKey()
    {
        $this->expectException(InvalidArgumentException::class);
        new Uuid47Transformer('tooshort');
    }

    public function testConstructorHashesLongKeys()
    {
        $v7 = new UuidV7('018f2d9f-9a2a-7def-8c3f-7b1a2c4d5e6f');

        $a = new Uuid47Transformer(random_bytes(32));
        $b = new Uuid47Transformer(random_bytes(32));

        $this->assertNotSame(
            $a->encode($v7)->toRfc4122(),
            $b->encode($v7)->toRfc4122(),
        );
    }

    /**
     * Test vectors from the reference C implementation.
     */
    #[DataProvider('provideEncodeDecodeVectors')]
    public function testEncode(string $inputV7, string $expectedV4)
    {
        $converter = $this->createConverter();
        $encoded = $converter->encode(new UuidV7($inputV7));

        $this->assertSame($expectedV4, $encoded->toRfc4122());
    }

    #[DataProvider('provideEncodeDecodeVectors')]
    public function testDecode(string $expectedV7, string $inputV4)
    {
        $converter = $this->createConverter();
        $decoded = $converter->decode(new UuidV4($inputV4));

        $this->assertSame($expectedV7, $decoded->toRfc4122());
    }

    #[DataProvider('provideEncodeDecodeVectors')]
    public function testRoundTrip(string $inputV7, string $expectedV4)
    {
        $converter = $this->createConverter();
        $v7 = new UuidV7($inputV7);

        $encoded = $converter->encode($v7);
        $this->assertInstanceOf(UuidV4::class, $encoded);

        $decoded = $converter->decode($encoded);
        $this->assertInstanceOf(UuidV7::class, $decoded);
        $this->assertSame($inputV7, $decoded->toRfc4122());
    }

    public static function provideEncodeDecodeVectors(): iterable
    {
        yield 'C demo.c example' => [
            '018f2d9f-9a2a-7def-8c3f-7b1a2c4d5e6f',
            '2463c780-7fca-4def-8c3f-7b1a2c4d5e6f',
        ];

        yield 'all zeros timestamp' => [
            '00000000-0000-7000-8000-000000000000',
            '22d97126-9609-4000-8000-000000000000',
        ];

        yield 'C roundtrip vector 0' => [
            '00000000-007b-7aaa-8123-456789abcdef',
            'b108050e-46b6-4aaa-8123-456789abcdef',
        ];

        yield 'C roundtrip vector 1' => [
            '00000010-007b-7aad-9032-547698badcfe',
            'bc75bd50-97ef-4aad-9032-547698badcfe',
        ];

        yield 'C roundtrip vector 2' => [
            '00000020-007b-7aa4-a301-6745ab89efcd',
            'a3e09c87-bf85-4aa4-a301-6745ab89efcd',
        ];
    }

    public function testRandomBitsPreserved()
    {
        $converter = $this->createConverter();
        $v7 = new UuidV7('018f2d9f-9a2a-7def-8c3f-7b1a2c4d5e6f');
        $v4 = $converter->encode($v7);

        $v7hex = str_replace('-', '', $v7->toRfc4122());
        $v4hex = str_replace('-', '', $v4->toRfc4122());

        // rand_a (lower nibble of byte 6 + byte 7) should be preserved
        $this->assertSame($v7hex[13], $v4hex[13], 'rand_a high nibble');
        $this->assertSame(substr($v7hex, 14, 2), substr($v4hex, 14, 2), 'rand_a low byte');

        // variant bits masked, but low 6 bits of byte 8 should be preserved
        $v7b8 = hexdec(substr($v7hex, 16, 2)) & 0x3F;
        $v4b8 = hexdec(substr($v4hex, 16, 2)) & 0x3F;
        $this->assertSame($v7b8, $v4b8, 'clock_seq high bits');

        // bytes 9-15 should be identical
        $this->assertSame(substr($v7hex, 18), substr($v4hex, 18), 'rand_b');
    }

    public function testEncodeReturnType()
    {
        $converter = $this->createConverter();
        $result = $converter->encode(new UuidV7());

        $this->assertInstanceOf(UuidV4::class, $result);
    }

    public function testDecodeReturnType()
    {
        $converter = $this->createConverter();
        $v4 = $converter->encode(new UuidV7());
        $result = $converter->decode($v4);

        $this->assertInstanceOf(UuidV7::class, $result);
    }

    public function testDifferentKeysProduceDifferentResults()
    {
        $v7 = new UuidV7('018f2d9f-9a2a-7def-8c3f-7b1a2c4d5e6f');

        $a = new Uuid47Transformer(random_bytes(16));
        $b = new Uuid47Transformer(random_bytes(16));

        $this->assertNotSame(
            $a->encode($v7)->toRfc4122(),
            $b->encode($v7)->toRfc4122(),
        );
    }

    public function testDecodeWithWrongKeyProducesDifferentResult()
    {
        $v7 = new UuidV7('018f2d9f-9a2a-7def-8c3f-7b1a2c4d5e6f');

        $correctKey = $this->createConverter();
        $wrongKey = new Uuid47Transformer(random_bytes(16));

        $encoded = $correctKey->encode($v7);

        // Decoding with a wrong key does not throw, but produces a different UUIDv7
        $decoded = $wrongKey->decode($encoded);
        $this->assertNotSame($v7->toRfc4122(), $decoded->toRfc4122());
    }
}
