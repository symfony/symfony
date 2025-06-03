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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Exception\InvalidArgumentException;
use Symfony\Component\Uid\MaxUlid;
use Symfony\Component\Uid\NilUlid;
use Symfony\Component\Uid\Tests\Fixtures\CustomUlid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV4;

class UlidTest extends TestCase
{
    /**
     * @group time-sensitive
     */
    public function testGenerate()
    {
        $a = new Ulid();
        $b = new Ulid();
        usleep(-10000);
        $c = new Ulid();

        $this->assertSame(0, strncmp($a, $b, 20));
        $this->assertSame(0, strncmp($a, $c, 20));
        $a = base_convert(strtr(substr($a, -6), 'ABCDEFGHJKMNPQRSTVWXYZ', 'abcdefghijklmnopqrstuv'), 32, 10);
        $b = base_convert(strtr(substr($b, -6), 'ABCDEFGHJKMNPQRSTVWXYZ', 'abcdefghijklmnopqrstuv'), 32, 10);
        $c = base_convert(strtr(substr($c, -6), 'ABCDEFGHJKMNPQRSTVWXYZ', 'abcdefghijklmnopqrstuv'), 32, 10);
        $this->assertSame(1, $b - $a);
        $this->assertSame(1, $c - $b);
    }

    public function testWithInvalidUlid()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ULID: "this is not a ulid".');

        new Ulid('this is not a ulid');
    }

    public function testBinary()
    {
        $ulid = new Ulid('00000000000000000000000000');
        $this->assertSame("\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0", $ulid->toBinary());

        $ulid = new Ulid('3zzzzzzzzzzzzzzzzzzzzzzzzz');
        $this->assertSame('7fffffffffffffffffffffffffffffff', bin2hex($ulid->toBinary()));

        $this->assertTrue($ulid->equals(Ulid::fromString(hex2bin('7fffffffffffffffffffffffffffffff'))));
    }

    public function toHex()
    {
        $ulid = Ulid::fromString('1BVXue8CnY8ogucrHX3TeF');
        $this->assertSame('0x0177058f4dacd0b2a990a49af02bc008', $ulid->toHex());
    }

    public function testFromUuid()
    {
        $uuid = new UuidV4();

        $ulid = Ulid::fromString($uuid);

        $this->assertSame($uuid->toBase32(), (string) $ulid);
        $this->assertSame($ulid->toBase32(), (string) $ulid);
        $this->assertSame((string) $uuid, $ulid->toRfc4122());
        $this->assertTrue($ulid->equals(Ulid::fromString($uuid)));
    }

    public function testBase58()
    {
        $ulid = new Ulid('00000000000000000000000000');
        $this->assertSame('1111111111111111111111', $ulid->toBase58());

        $ulid = Ulid::fromString("\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF");
        $this->assertSame('YcVfxkQb6JRzqk5kF2tNLv', $ulid->toBase58());
        $this->assertTrue($ulid->equals(Ulid::fromString('YcVfxkQb6JRzqk5kF2tNLv')));
    }

    /**
     * @group time-sensitive
     */
    public function testGetDateTime()
    {
        $time = microtime(false);
        $ulid = new Ulid();
        $time = substr($time, 11).substr($time, 1, 4);

        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', $time), $ulid->getDateTime());

        $this->assertEquals(new \DateTimeImmutable('@0'), (new Ulid('000000000079KA1307SR9X4MV3'))->getDateTime());
        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', '0.001'), (new Ulid('000000000179KA1307SR9X4MV3'))->getDateTime());
        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', '281474976710.654'), (new Ulid('7ZZZZZZZZY79KA1307SR9X4MV3'))->getDateTime());
        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', '281474976710.655'), (new Ulid('7ZZZZZZZZZ79KA1307SR9X4MV3'))->getDateTime());
    }

    public function testIsValid()
    {
        $this->assertFalse(Ulid::isValid('not a ulid'));
        $this->assertTrue(Ulid::isValid('00000000000000000000000000'));
    }

    public function testEquals()
    {
        $a = new Ulid();
        $b = new Ulid();

        $this->assertTrue($a->equals($a));
        $this->assertFalse($a->equals($b));
        $this->assertFalse($a->equals((string) $a));
    }

    /**
     * @group time-sensitive
     */
    public function testCompare()
    {
        $a = new Ulid();
        $b = new Ulid();

        $this->assertSame(0, $a->compare($a));
        $this->assertLessThan(0, $a->compare($b));
        $this->assertGreaterThan(0, $b->compare($a));

        usleep(1001);
        $c = new Ulid();

        $this->assertLessThan(0, $b->compare($c));
        $this->assertGreaterThan(0, $c->compare($b));
    }

    public function testFromBinary()
    {
        $this->assertEquals(
            Ulid::fromString("\x01\x77\x05\x8F\x4D\xAC\xD0\xB2\xA9\x90\xA4\x9A\xF0\x2B\xC0\x08"),
            Ulid::fromBinary("\x01\x77\x05\x8F\x4D\xAC\xD0\xB2\xA9\x90\xA4\x9A\xF0\x2B\xC0\x08")
        );
    }

    /**
     * @dataProvider provideInvalidBinaryFormat
     */
    public function testFromBinaryInvalidFormat(string $ulid)
    {
        $this->expectException(InvalidArgumentException::class);

        Ulid::fromBinary($ulid);
    }

    public static function provideInvalidBinaryFormat(): array
    {
        return [
            ['01EW2RYKDCT2SAK454KBR2QG08'],
            ['1BVXue8CnY8ogucrHX3TeF'],
            ['0177058f-4dac-d0b2-a990-a49af02bc008'],
        ];
    }

    public function testFromBase58()
    {
        $this->assertEquals(
            Ulid::fromString('1BVXue8CnY8ogucrHX3TeF'),
            Ulid::fromBase58('1BVXue8CnY8ogucrHX3TeF')
        );
    }

    /**
     * @dataProvider provideInvalidBase58Format
     */
    public function testFromBase58InvalidFormat(string $ulid)
    {
        $this->expectException(InvalidArgumentException::class);

        Ulid::fromBase58($ulid);
    }

    public static function provideInvalidBase58Format(): array
    {
        return [
            ["\x01\x77\x05\x8F\x4D\xAC\xD0\xB2\xA9\x90\xA4\x9A\xF0\x2B\xC0\x08"],
            ['01EW2RYKDCT2SAK454KBR2QG08'],
            ['0177058f-4dac-d0b2-a990-a49af02bc008'],
        ];
    }

    public function testFromBase32()
    {
        $this->assertEquals(
            Ulid::fromString('01EW2RYKDCT2SAK454KBR2QG08'),
            Ulid::fromBase32('01EW2RYKDCT2SAK454KBR2QG08')
        );
    }

    /**
     * @dataProvider provideInvalidBase32Format
     */
    public function testFromBase32InvalidFormat(string $ulid)
    {
        $this->expectException(InvalidArgumentException::class);

        Ulid::fromBase32($ulid);
    }

    public static function provideInvalidBase32Format(): array
    {
        return [
            ["\x01\x77\x05\x8F\x4D\xAC\xD0\xB2\xA9\x90\xA4\x9A\xF0\x2B\xC0\x08"],
            ['1BVXue8CnY8ogucrHX3TeF'],
            ['0177058f-4dac-d0b2-a990-a49af02bc008'],
        ];
    }

    public function testFromRfc4122()
    {
        $this->assertEquals(
            Ulid::fromString('0177058f-4dac-d0b2-a990-a49af02bc008'),
            Ulid::fromRfc4122('0177058f-4dac-d0b2-a990-a49af02bc008')
        );
    }

    /**
     * @dataProvider provideInvalidRfc4122Format
     */
    public function testFromRfc4122InvalidFormat(string $ulid)
    {
        $this->expectException(InvalidArgumentException::class);

        Ulid::fromRfc4122($ulid);
    }

    public static function provideInvalidRfc4122Format(): array
    {
        return [
            ["\x01\x77\x05\x8F\x4D\xAC\xD0\xB2\xA9\x90\xA4\x9A\xF0\x2B\xC0\x08"],
            ['01EW2RYKDCT2SAK454KBR2QG08'],
            ['1BVXue8CnY8ogucrHX3TeF'],
        ];
    }

    public function testFromStringOnExtendedClassReturnsStatic()
    {
        $this->assertInstanceOf(CustomUlid::class, CustomUlid::fromString((new CustomUlid())->toBinary()));
    }

    public function testFromStringBase58Padding()
    {
        $this->assertInstanceOf(Ulid::class, Ulid::fromString('111111111u9QRyVM94rdmZ'));
    }

    /**
     * @testWith    ["00000000-0000-0000-0000-000000000000"]
     *              ["1111111111111111111111"]
     *              ["00000000000000000000000000"]
     */
    public function testNilUlid(string $ulid)
    {
        $ulid = Ulid::fromString($ulid);

        $this->assertInstanceOf(NilUlid::class, $ulid);
        $this->assertSame('00000000000000000000000000', (string) $ulid);
    }

    public function testNewNilUlid()
    {
        $this->assertSame('00000000000000000000000000', (string) new NilUlid());
    }

    /**
     * @testWith    ["ffffffff-ffff-ffff-ffff-ffffffffffff"]
     *              ["7zzzzzzzzzzzzzzzzzzzzzzzzz"]
     */
    public function testMaxUlid(string $ulid)
    {
        $ulid = Ulid::fromString($ulid);

        $this->assertInstanceOf(MaxUlid::class, $ulid);
        $this->assertSame('7ZZZZZZZZZZZZZZZZZZZZZZZZZ', (string) $ulid);
    }

    public function testNewMaxUlid()
    {
        $this->assertSame('7ZZZZZZZZZZZZZZZZZZZZZZZZZ', (string) new MaxUlid());
    }

    public function testToString()
    {
        $this->assertSame('01HK77WP8T7107EZH9CNAES202', (new Ulid('01HK77WP8T7107EZH9CNAES202'))->toString());
    }
}
