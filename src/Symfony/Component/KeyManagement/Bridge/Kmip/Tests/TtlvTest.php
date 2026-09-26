<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Kmip\Kmip\Ttlv;
use Symfony\Component\KeyManagement\Bridge\Kmip\KmipVersion;
use Symfony\Component\KeyManagement\Bridge\Kmip\Tests\Fixtures\RedactedTraceAssertionsTrait;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

final class TtlvTest extends TestCase
{
    use RedactedTraceAssertionsTrait;

    private const int TAG_BATCH_COUNT = 0x42000D;
    private const int TAG_SAMPLE_ITEM = 0x420020;
    private const int TAG_RESPONSE_MESSAGE = 0x42007B;
    private const int TAG_USERNAME = 0x420099;
    private const int TAG_DATA = 0x4200C2;
    private const int TAG_VENDOR_DEFINED_TEST_FIELD = 0x540001;

    public function testTextAndBinaryPayloadKeepNonzeroPadding()
    {
        $items = Ttlv::items(hex2bin('4200990700000003616263ffffffff00'), KmipVersion::Version14);
        $this->assertSame([['tag' => self::TAG_USERNAME, 'type' => Ttlv::TEXT, 'value' => 'abc']], $items);
        $this->assertSame("\0\1\2", Ttlv::requireType(Ttlv::items(hex2bin('4200c20800000003000102ffffffff00'), KmipVersion::Version14)[0], self::TAG_DATA, Ttlv::BYTES));
        $this->assertSame(255, Ttlv::number(Ttlv::items(hex2bin('4200200500000004000000ffffffff00'), KmipVersion::Version14)[0], self::TAG_SAMPLE_ITEM, Ttlv::ENUMERATION));
        $this->assertSame('4200c208000000030001020000000000', bin2hex(Ttlv::bytes(self::TAG_DATA, "\0\1\2")));
    }

    public function testInvalidUtf8CredentialIsRedactedFromTheEncodingTrace()
    {
        $secret = "password-marker\xFF";
        $trace = self::traceOf(static fn () => Ttlv::text(self::TAG_USERNAME, $secret));

        self::assertRedacted($secret, $trace);
    }

    public function testTextRequiresUtf8ButBytesAllowArbitraryData()
    {
        $this->assertSame("\xFF", Ttlv::requireType(Ttlv::items(Ttlv::bytes(self::TAG_SAMPLE_ITEM, "\xFF"), KmipVersion::Version14)[0], self::TAG_SAMPLE_ITEM, Ttlv::BYTES));

        try {
            Ttlv::text(self::TAG_SAMPLE_ITEM, "\xFF");
            $this->fail('Invalid UTF-8 text must not be encoded.');
        } catch (RuntimeException) {
        }

        $this->expectException(RuntimeException::class);
        Ttlv::items(hex2bin('4200200700000001ff00000000000000'), KmipVersion::Version14);
    }

    #[DataProvider('specExamples')]
    public function testIndependentTtlvSpecExamples(int $tag, int $type, string $valueHex, string $expectedHex)
    {
        $encoded = Ttlv::encode($tag, $type, hex2bin($valueHex));
        $this->assertSame(strtolower($expectedHex), bin2hex($encoded));
        $this->assertSame(hex2bin($valueHex), Ttlv::requireType(Ttlv::items($encoded, KmipVersion::Version14)[0], $tag, $type));
    }

    public static function specExamples(): iterable
    {
        yield 'Integer 8' => [self::TAG_SAMPLE_ITEM, Ttlv::INTEGER, '00000008', '42002002000000040000000800000000'];
        yield 'Long Integer' => [self::TAG_SAMPLE_ITEM, Ttlv::LONG_INTEGER, '01b69b4ba5749200', '420020030000000801b69b4ba5749200'];
        yield 'positive Big Integer' => [self::TAG_SAMPLE_ITEM, Ttlv::BIG_INTEGER, '0000000003fd35eb6bc2df4618080000', '42002004000000100000000003fd35eb6bc2df4618080000'];
        yield 'negative Big Integer' => [self::TAG_SAMPLE_ITEM, Ttlv::BIG_INTEGER, 'ffffffffffffffff', '4200200400000008ffffffffffffffff'];
        yield 'Big Integer high bit' => [self::TAG_SAMPLE_ITEM, Ttlv::BIG_INTEGER, '0000000000000080', '42002004000000080000000000000080'];
        yield 'Enumeration 255' => [self::TAG_SAMPLE_ITEM, Ttlv::ENUMERATION, '000000ff', '4200200500000004000000ff00000000'];
        yield 'Boolean true' => [self::TAG_SAMPLE_ITEM, Ttlv::BOOLEAN, '0000000000000001', '42002006000000080000000000000001'];
        yield 'Text String' => [self::TAG_SAMPLE_ITEM, Ttlv::TEXT, '48656c6c6f20576f726c64', '420020070000000b48656c6c6f20576f726c640000000000'];
        yield 'Byte String' => [self::TAG_SAMPLE_ITEM, Ttlv::BYTES, '010203', '42002008000000030102030000000000'];
        yield 'Date-Time' => [self::TAG_SAMPLE_ITEM, Ttlv::DATE_TIME, '0000000047da67f8', '42002009000000080000000047da67f8'];
        yield 'Interval' => [self::TAG_SAMPLE_ITEM, Ttlv::INTERVAL, '000d2f00', '4200200a00000004000d2f0000000000'];
        yield 'Structure' => [self::TAG_SAMPLE_ITEM, Ttlv::STRUCTURE, '4200040500000004000000fe000000004200050200000004000000ff00000000', '42002001000000204200040500000004000000fe000000004200050200000004000000ff00000000'];
    }

    #[DataProvider('extendedDateTimes')]
    public function testDateTimeExtendedUsesSignedMicroseconds(KmipVersion $version, string $valueHex)
    {
        $encoded = Ttlv::encode(self::TAG_VENDOR_DEFINED_TEST_FIELD, Ttlv::DATE_TIME_EXTENDED, hex2bin($valueHex));

        $this->assertSame('5400010b00000008'.$valueHex, bin2hex($encoded));
        $this->assertSame(hex2bin($valueHex), Ttlv::requireType(Ttlv::items($encoded, $version)[0], self::TAG_VENDOR_DEFINED_TEST_FIELD, Ttlv::DATE_TIME_EXTENDED));
    }

    public static function extendedDateTimes(): iterable
    {
        yield '2.0 one second and one microsecond after epoch' => [KmipVersion::Version20, '00000000000f4241'];
        yield '2.1 one microsecond before epoch' => [KmipVersion::Version21, 'ffffffffffffffff'];
    }

    public function testVersion14RejectsDateTimeExtendedButAcceptsLegacyDateTime()
    {
        $legacy = Ttlv::encode(self::TAG_SAMPLE_ITEM, Ttlv::DATE_TIME, hex2bin('0000000000000001'));
        $this->assertSame(hex2bin('0000000000000001'), Ttlv::items($legacy, KmipVersion::Version14)[0]['value']);

        $extended = Ttlv::structure(self::TAG_SAMPLE_ITEM, Ttlv::encode(self::TAG_VENDOR_DEFINED_TEST_FIELD, Ttlv::DATE_TIME_EXTENDED, hex2bin('00000000000f4241')));
        $this->expectException(RuntimeException::class);
        Ttlv::children(Ttlv::items($extended, KmipVersion::Version14)[0], self::TAG_SAMPLE_ITEM, KmipVersion::Version14);
    }

    public function testRejectsChildWhoseDeclaredLengthExceedsParentStructureBoundary()
    {
        $frame = hex2bin('42007b010000000842007a010000000842000d02000000040000000100000000');
        $items = Ttlv::items($frame, KmipVersion::Version14);
        $this->assertCount(2, $items);

        $this->expectException(RuntimeException::class);
        Ttlv::children($items[0], self::TAG_RESPONSE_MESSAGE, KmipVersion::Version14);
    }

    #[DataProvider('malformedItems')]
    public function testMalformedItemIsRejected(string $hex)
    {
        $this->expectException(RuntimeException::class);
        Ttlv::items(hex2bin($hex), KmipVersion::Version20);
    }

    public static function malformedItems(): iterable
    {
        yield 'partial header' => ['42009907000000'];
        yield 'missing value' => ['42009907000000036162'];
        yield 'missing padding' => ['4200990700000003616263'];
        yield 'length exceeds frame' => ['42009907ffffffff'];
        yield 'zero tail' => ['4200990700000003616263000000000000000000'];
        yield 'truncated structure' => ['42007b010000001042007a0100000008'];
        yield 'zero tag' => ['0000000700000000'];
        yield 'unknown type' => ['4200200c00000000'];
        yield 'unaligned structure' => ['42002001000000016100000000000000'];
        yield 'integer with long length' => ['42002002000000080000000100000000'];
        yield 'enumeration with long length' => ['42002005000000080000000100000000'];
        yield 'long integer with short length' => ['42002003000000040000000100000000'];
        yield 'boolean with invalid value' => ['42002006000000080000000000000002'];
        yield 'date-time with short length' => ['42002009000000040000000100000000'];
        yield 'interval with long length' => ['4200200a000000080000000100000000'];
        yield 'date-time extended with short length' => ['4200200b000000040000000100000000'];
    }

    public function testParserRejectsAnInvalidNumericItemLength()
    {
        $this->expectException(RuntimeException::class);
        Ttlv::number(Ttlv::items(hex2bin('42000d02000000080000000100000000'), KmipVersion::Version14)[0], self::TAG_BATCH_COUNT);
    }

    public function testNumericFieldRejectsAValueWithTheWrongLength()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid KMIP numeric field.');
        Ttlv::number(['tag' => self::TAG_BATCH_COUNT, 'type' => Ttlv::INTEGER, 'value' => "\0\0\0"], self::TAG_BATCH_COUNT);
    }

    public function testNumberRejectsANonnumericTtlvType()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid KMIP numeric type.');

        Ttlv::number(['tag' => self::TAG_SAMPLE_ITEM, 'type' => Ttlv::TEXT, 'value' => 'value'], self::TAG_SAMPLE_ITEM, Ttlv::TEXT);
    }

    public function testIntegerUsesSignedTwoComplementWhileEnumerationAndIntervalAreUnsigned()
    {
        $negative = Ttlv::items(Ttlv::integer(self::TAG_SAMPLE_ITEM, -1), KmipVersion::Version14)[0];
        $this->assertSame('ffffffff', bin2hex($negative['value']));
        $this->assertSame(-1, Ttlv::number($negative, self::TAG_SAMPLE_ITEM));
        $this->assertSame(~0x7FFFFFFF, Ttlv::number(Ttlv::items(Ttlv::integer(self::TAG_SAMPLE_ITEM, ~0x7FFFFFFF), KmipVersion::Version14)[0], self::TAG_SAMPLE_ITEM));
        foreach ([Ttlv::ENUMERATION, Ttlv::INTERVAL] as $type) {
            $encoded = Ttlv::integer(self::TAG_SAMPLE_ITEM, '0xFFFFFFFF', $type);
            $this->assertSame('ffffffff', bin2hex(Ttlv::items($encoded, KmipVersion::Version14)[0]['value']));
            $this->assertSame(4 === \PHP_INT_SIZE ? '0xFFFFFFFF' : 4294967295, Ttlv::number(Ttlv::items($encoded, KmipVersion::Version14)[0], self::TAG_SAMPLE_ITEM, $type));
        }
    }

    #[DataProvider('outOfRangeIntegers')]
    public function testEncoderRejectsValuesOutsideTheTtlvNumericType(int|string $value, int $type)
    {
        $this->expectException(RuntimeException::class);
        Ttlv::integer(self::TAG_SAMPLE_ITEM, $value, $type);
    }

    public static function outOfRangeIntegers(): iterable
    {
        if (\PHP_INT_SIZE > 4) {
            yield 'signed above maximum' => [2147483648, Ttlv::INTEGER];
            yield 'signed below minimum' => [-2147483649, Ttlv::INTEGER];
            yield 'unsigned above maximum' => [4294967296, Ttlv::ENUMERATION];
        }
        yield 'negative enumeration' => [-1, Ttlv::ENUMERATION];
        yield 'negative interval' => [-1, Ttlv::INTERVAL];
        yield 'unsigned hex above maximum' => ['0x100000000', Ttlv::ENUMERATION];
        yield 'signed hex is invalid' => ['0x80000000', Ttlv::INTEGER];
    }

    public function testEncoderRejectsAnItemLargerThanOneFrame()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('KMIP item exceeds the maximum frame size.');
        Ttlv::bytes(self::TAG_DATA, str_repeat('x', Ttlv::MAX_FRAME));
    }
}
