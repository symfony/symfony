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
use Symfony\Component\Uid\MaxUuid;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Tests\Fixtures\CustomUuid;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;
use Symfony\Component\Uid\UuidV7;

class UuidTest extends TestCase
{
    private const A_UUID_V1 = 'd9e7a184-5d5b-11ea-a62a-3499710062d0';
    private const A_UUID_V4 = 'd6b3345b-2905-4048-a83c-b5988e765d98';
    private const A_UUID_V7 = '017f22e2-79b0-7cc3-98c4-dc0c0c07398f';

    /**
     * @dataProvider provideInvalidUuids
     */
    public function testConstructorWithInvalidUuid(string $uuid)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID: "'.$uuid.'".');

        Uuid::fromString($uuid);
    }

    public static function provideInvalidUuids(): iterable
    {
        yield ['this is not a uuid'];
        yield ['these are just thirty-six characters'];
    }

    /**
     * @dataProvider provideInvalidVariant
     */
    public function testInvalidVariant(string $uuid)
    {
        $uuid = new Uuid($uuid);
        $this->assertFalse(Uuid::isValid($uuid));

        $uuid = (string) $uuid;
        $class = Uuid::class.'V'.$uuid[14];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUIDv'.$uuid[14].': "'.$uuid.'".');

        new $class($uuid);
    }

    public static function provideInvalidVariant(): iterable
    {
        yield ['8dac64d3-937a-1e7c-fa1d-d5d6c06a61f5'];
        yield ['8dac64d3-937a-3e7c-fa1d-d5d6c06a61f5'];
        yield ['8dac64d3-937a-4e7c-fa1d-d5d6c06a61f5'];
        yield ['8dac64d3-937a-5e7c-fa1d-d5d6c06a61f5'];
        yield ['8dac64d3-937a-6e7c-fa1d-d5d6c06a61f5'];
        yield ['8dac64d3-937a-7e7c-fa1d-d5d6c06a61f5'];
        yield ['8dac64d3-937a-8e7c-fa1d-d5d6c06a61f5'];
    }

    public function testConstructorWithValidUuid()
    {
        $uuid = new UuidV4(self::A_UUID_V4);

        $this->assertSame(self::A_UUID_V4, (string) $uuid);
        $this->assertSame('"'.self::A_UUID_V4.'"', json_encode($uuid));
    }

    public function testV1()
    {
        $uuid = Uuid::v1();

        $this->assertInstanceOf(UuidV1::class, $uuid);

        $uuid = new UuidV1(self::A_UUID_V1);

        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', '1583245966.746458'), $uuid->getDateTime());
        $this->assertSame('3499710062d0', $uuid->getNode());
    }

    public function testV1IsLowerCase()
    {
        $uuid = new UuidV1();
        $this->assertSame(strtolower((string) $uuid), (string) $uuid);

        $uuid = new UuidV1('D9E7A184-5D5B-11EA-A62A-3499710062D0');
        $this->assertSame(strtolower((string) $uuid), (string) $uuid);
    }

    public function testV3()
    {
        $uuid = Uuid::v3(new UuidV4(self::A_UUID_V4), 'the name');

        $this->assertInstanceOf(UuidV3::class, $uuid);
        $this->assertSame('8dac64d3-937a-3e7c-aa1d-d5d6c06a61f5', (string) $uuid);
    }

    public function testV4()
    {
        $uuid = Uuid::v4();

        $this->assertInstanceOf(UuidV4::class, $uuid);
    }

    public function testV5()
    {
        $uuid = Uuid::v5(new UuidV4('ec07aa88-f84e-47b9-a581-1c6b30a2f484'), 'the name');

        $this->assertInstanceOf(UuidV5::class, $uuid);
        $this->assertSame('851def0c-b9c7-55aa-a991-130e769ec0a9', (string) $uuid);
    }

    public function testV6()
    {
        $uuid = Uuid::v6();

        $this->assertInstanceOf(UuidV6::class, $uuid);

        $uuid = new UuidV6(substr_replace(self::A_UUID_V1, '6', 14, 1));

        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', '85916308548.278321'), $uuid->getDateTime());
        $this->assertSame('3499710062d0', $uuid->getNode());
    }

    public function testV6IsSeeded()
    {
        $uuidV1 = Uuid::v1();
        $uuidV6 = Uuid::v6();

        $this->assertNotSame(substr($uuidV1, 24), substr($uuidV6, 24));
    }

    public function testV7()
    {
        $uuid = Uuid::fromString(self::A_UUID_V7);

        $this->assertInstanceOf(UuidV7::class, $uuid);
        $this->assertSame(1645557742, $uuid->getDateTime()->getTimeStamp());

        $prev = UuidV7::generate();

        for ($i = 0; $i < 25; ++$i) {
            $uuid = UuidV7::generate();
            $now = gmdate('Y-m-d H:i');
            $this->assertGreaterThan($prev, $uuid);
            $prev = $uuid;
        }

        $this->assertTrue(Uuid::isValid($uuid));
        $uuid = Uuid::fromString($uuid);
        $this->assertInstanceOf(UuidV7::class, $uuid);
        $this->assertSame($now, $uuid->getDateTime()->format('Y-m-d H:i'));
    }

    public function testBinary()
    {
        $uuid = new UuidV4(self::A_UUID_V4);
        $uuid = Uuid::fromString($uuid->toBinary());

        $this->assertInstanceOf(UuidV4::class, $uuid);
        $this->assertSame(self::A_UUID_V4, (string) $uuid);
    }

    public function testHex()
    {
        $uuid = new UuidV4(self::A_UUID_V4);

        $this->assertSame('0xd6b3345b29054048a83cb5988e765d98', $uuid->toHex());
    }

    public function testFromUlid()
    {
        $ulid = new Ulid();
        $uuid = Uuid::fromString($ulid);

        $this->assertSame((string) $ulid, $uuid->toBase32());
        $this->assertSame((string) $uuid, $uuid->toRfc4122());
        $this->assertTrue($uuid->equals(Uuid::fromString($ulid)));
    }

    public function testBase58()
    {
        $uuid = new NilUuid();
        $this->assertSame('1111111111111111111111', $uuid->toBase58());

        $uuid = Uuid::fromString("\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF");
        $this->assertSame('YcVfxkQb6JRzqk5kF2tNLv', $uuid->toBase58());
        $this->assertTrue($uuid->equals(Uuid::fromString('YcVfxkQb6JRzqk5kF2tNLv')));
    }

    public function testIsValid()
    {
        $this->assertFalse(Uuid::isValid('not a uuid'));
        $this->assertTrue(Uuid::isValid(self::A_UUID_V4));
        $this->assertFalse(UuidV4::isValid(self::A_UUID_V1));
        $this->assertTrue(UuidV4::isValid(self::A_UUID_V4));
    }

    public function testIsValidWithVariousFormat()
    {
        $uuid = Uuid::v4();

        $this->assertTrue(Uuid::isValid($uuid->toBase32(), Uuid::FORMAT_BASE_32));
        $this->assertFalse(Uuid::isValid($uuid->toBase58(), Uuid::FORMAT_BASE_32));
        $this->assertFalse(Uuid::isValid($uuid->toBinary(), Uuid::FORMAT_BASE_32));
        $this->assertFalse(Uuid::isValid($uuid->toRfc4122(), Uuid::FORMAT_BASE_32));

        $this->assertFalse(Uuid::isValid($uuid->toBase32(), Uuid::FORMAT_BASE_58));
        $this->assertTrue(Uuid::isValid($uuid->toBase58(), Uuid::FORMAT_BASE_58));
        $this->assertFalse(Uuid::isValid($uuid->toBinary(), Uuid::FORMAT_BASE_58));
        $this->assertFalse(Uuid::isValid($uuid->toRfc4122(), Uuid::FORMAT_BASE_58));

        $this->assertFalse(Uuid::isValid($uuid->toBase32(), Uuid::FORMAT_BINARY));
        $this->assertFalse(Uuid::isValid($uuid->toBase58(), Uuid::FORMAT_BINARY));
        $this->assertTrue(Uuid::isValid($uuid->toBinary(), Uuid::FORMAT_BINARY));
        $this->assertFalse(Uuid::isValid($uuid->toRfc4122(), Uuid::FORMAT_BINARY));

        $this->assertFalse(Uuid::isValid($uuid->toBase32(), Uuid::FORMAT_RFC_4122));
        $this->assertFalse(Uuid::isValid($uuid->toBase58(), Uuid::FORMAT_RFC_4122));
        $this->assertFalse(Uuid::isValid($uuid->toBinary(), Uuid::FORMAT_RFC_4122));
        $this->assertTrue(Uuid::isValid($uuid->toRfc4122(), Uuid::FORMAT_RFC_4122));

        $this->assertFalse(Uuid::isValid($uuid->toBase32(), Uuid::FORMAT_RFC_9562));
        $this->assertFalse(Uuid::isValid($uuid->toBase58(), Uuid::FORMAT_RFC_9562));
        $this->assertFalse(Uuid::isValid($uuid->toBinary(), Uuid::FORMAT_RFC_9562));
        $this->assertTrue(Uuid::isValid($uuid->toRfc4122(), Uuid::FORMAT_RFC_9562));

        $this->assertTrue(Uuid::isValid($uuid->toBase32(), Uuid::FORMAT_ALL));
        $this->assertTrue(Uuid::isValid($uuid->toBase58(), Uuid::FORMAT_ALL));
        $this->assertTrue(Uuid::isValid($uuid->toBinary(), Uuid::FORMAT_ALL));
        $this->assertTrue(Uuid::isValid($uuid->toRfc4122(), Uuid::FORMAT_ALL));

        $this->assertFalse(Uuid::isValid('30J7CNpDMfXPZrCsn4Cgey', Uuid::FORMAT_BASE_58), 'Fake base-58 string with the "O" forbidden char is not valid');
    }

    public function testIsValidWithNilUuid()
    {
        $this->assertTrue(Uuid::isValid('00000000-0000-0000-0000-000000000000'));
        $this->assertTrue(NilUuid::isValid('00000000-0000-0000-0000-000000000000'));

        $this->assertFalse(UuidV1::isValid('00000000-0000-0000-0000-000000000000'));
        $this->assertFalse(UuidV4::isValid('00000000-0000-0000-0000-000000000000'));
    }

    public function testIsValidWithMaxUuid()
    {
        $this->assertTrue(Uuid::isValid('ffffffff-ffff-ffff-ffff-ffffffffffff'));
        $this->assertTrue(Uuid::isValid('FFFFFFFF-FFFF-FFFF-FFFF-FFFFFFFFFFFF'));
        $this->assertTrue(Uuid::isValid('fFFFFFFF-ffff-FFFF-FFFF-FFFFffFFFFFF'));

        $this->assertFalse(UuidV5::isValid('ffffffff-ffff-ffff-ffff-ffffffffffff'));
        $this->assertFalse(UuidV6::isValid('ffffffff-ffff-ffff-ffff-ffffffffffff'));
    }

    public function testEquals()
    {
        $uuid1 = new UuidV1(self::A_UUID_V1);
        $uuid2 = new UuidV4(self::A_UUID_V4);

        $this->assertTrue($uuid1->equals($uuid1));
        $this->assertFalse($uuid1->equals($uuid2));
    }

    /**
     * @dataProvider provideInvalidEqualType
     */
    public function testEqualsAgainstOtherType($other)
    {
        $this->assertFalse((new UuidV4(self::A_UUID_V4))->equals($other));
    }

    public static function provideInvalidEqualType(): iterable
    {
        yield [null];
        yield [self::A_UUID_V1];
        yield [self::A_UUID_V4];
        yield [new \stdClass()];
    }

    public function testHashable()
    {
        $uuid1 = new UuidV4(self::A_UUID_V4);
        $uuid2 = new UuidV4(self::A_UUID_V4);

        $this->assertSame($uuid1->hash(), $uuid2->hash());
    }

    /** @requires extension ds */
    public function testDsCompatibility()
    {
        $uuid1 = new UuidV4(self::A_UUID_V4);
        $uuid2 = new UuidV4(self::A_UUID_V4);

        $set = new \Ds\Set();
        $set->add($uuid1);
        $set->add($uuid2);

        $this->assertTrue($set->contains($uuid1));
        $this->assertTrue($set->contains($uuid2));
        $this->assertCount(1, $set);
    }

    public function testCompare()
    {
        $uuids = [];

        $uuids[] = $b = new Uuid('00000000-0000-0000-0000-00000000000b');
        $uuids[] = $a = new Uuid('00000000-0000-0000-0000-00000000000a');
        $uuids[] = $d = new Uuid('00000000-0000-0000-0000-00000000000d');
        $uuids[] = $c = new Uuid('00000000-0000-0000-0000-00000000000c');

        $this->assertNotSame([$a, $b, $c, $d], $uuids);

        usort($uuids, static fn (Uuid $a, Uuid $b): int => $a->compare($b));

        $this->assertSame([$a, $b, $c, $d], $uuids);
    }

    /**
     * @testWith    ["00000000-0000-0000-0000-000000000000"]
     *              ["1111111111111111111111"]
     *              ["00000000000000000000000000"]
     */
    public function testNilUuid(string $uuid)
    {
        $uuid = Uuid::fromString($uuid);

        $this->assertInstanceOf(NilUuid::class, $uuid);
        $this->assertSame('00000000-0000-0000-0000-000000000000', (string) $uuid);
    }

    public function testNewNilUuid()
    {
        $this->assertSame('00000000-0000-0000-0000-000000000000', (string) new NilUuid());
    }

    /**
     * @testWith    ["ffffffff-ffff-ffff-ffff-ffffffffffff"]
     *              ["7zzzzzzzzzzzzzzzzzzzzzzzzz"]
     */
    public function testMaxUuid(string $uuid)
    {
        $uuid = Uuid::fromString($uuid);

        $this->assertInstanceOf(MaxUuid::class, $uuid);
        $this->assertSame('ffffffff-ffff-ffff-ffff-ffffffffffff', (string) $uuid);
    }

    public function testNewMaxUuid()
    {
        $this->assertSame('ffffffff-ffff-ffff-ffff-ffffffffffff', (string) new MaxUuid());
    }

    public function testFromBinary()
    {
        $this->assertEquals(
            Uuid::fromString("\x01\x77\x05\x8F\x4D\xAC\xD0\xB2\xA9\x90\xA4\x9A\xF0\x2B\xC0\x08"),
            Uuid::fromBinary("\x01\x77\x05\x8F\x4D\xAC\xD0\xB2\xA9\x90\xA4\x9A\xF0\x2B\xC0\x08")
        );
    }

    /**
     * @dataProvider provideInvalidBinaryFormat
     */
    public function testFromBinaryInvalidFormat(string $ulid)
    {
        $this->expectException(InvalidArgumentException::class);

        Uuid::fromBinary($ulid);
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
            UuidV1::fromString('94fSqj9oxGtsNbkfQNntwx'),
            UuidV1::fromBase58('94fSqj9oxGtsNbkfQNntwx')
        );
    }

    /**
     * @dataProvider provideInvalidBase58Format
     */
    public function testFromBase58InvalidFormat(string $ulid)
    {
        $this->expectException(InvalidArgumentException::class);

        Uuid::fromBase58($ulid);
    }

    public static function provideInvalidBase58Format(): array
    {
        return [
            ["\x41\x4C\x08\x92\x57\x1B\x11\xEB\xBF\x70\x93\xF9\xB0\x82\x2C\x57"],
            ['219G494NRV27NVYW4KZ6R84B2Q'],
            ['414c0892-571b-11eb-bf70-93f9b0822c57'],
        ];
    }

    public function testFromBase32()
    {
        $this->assertEquals(
            UuidV5::fromString('2VN0S74HBDBB0AQRXAHFVG35KK'),
            UuidV5::fromBase32('2VN0S74HBDBB0AQRXAHFVG35KK')
        );
    }

    /**
     * @dataProvider provideInvalidBase32Format
     */
    public function testFromBase32InvalidFormat(string $ulid)
    {
        $this->expectException(InvalidArgumentException::class);

        Uuid::fromBase32($ulid);
    }

    public static function provideInvalidBase32Format(): array
    {
        return [
            ["\x5B\xA8\x32\x72\x45\x6D\x5A\xC0\xAB\xE3\xAA\x8B\xF7\x01\x96\x73"],
            ['CKTRYycTes6WAqSQJsTDaz'],
            ['5ba83272-456d-5ac0-abe3-aa8bf7019673'],
        ];
    }

    public function testFromRfc4122()
    {
        $this->assertEquals(
            UuidV6::fromString('1eb571b4-14c0-6893-bf70-2d4c83cf755a'),
            UuidV6::fromRfc4122('1eb571b4-14c0-6893-bf70-2d4c83cf755a')
        );
    }

    /**
     * @dataProvider provideInvalidRfc4122Format
     */
    public function testFromRfc4122InvalidFormat(string $ulid)
    {
        $this->expectException(InvalidArgumentException::class);

        Uuid::fromRfc4122($ulid);
    }

    public static function provideInvalidRfc4122Format(): array
    {
        return [
            ["\x1E\xB5\x71\xB4\x14\xC0\x68\x93\xBF\x70\x2D\x4C\x83\xCF\x75\x5A"],
            ['0YPNRV8560D29VYW1D9J1WYXAT'],
            ['4nwTLZ2TdMtTVDE5AwVjaR'],
        ];
    }

    public function testFromStringOnExtendedClassReturnsStatic()
    {
        $this->assertInstanceOf(CustomUuid::class, CustomUuid::fromString(self::A_UUID_V4));
    }

    public function testGetDateTime()
    {
        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', '103072857660.684697'), (new UuidV1('ffffffff-ffff-1fff-a456-426655440000'))->getDateTime());
        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', '0.000001'), (new UuidV1('1381400a-1dd2-11b2-a456-426655440000'))->getDateTime());
        $this->assertEquals(new \DateTimeImmutable('@0'), (new UuidV1('13814001-1dd2-11b2-a456-426655440000'))->getDateTime());
        $this->assertEquals(new \DateTimeImmutable('@0'), (new UuidV1('13814000-1dd2-11b2-a456-426655440000'))->getDateTime());
        $this->assertEquals(new \DateTimeImmutable('@0'), (new UuidV1('13813fff-1dd2-11b2-a456-426655440000'))->getDateTime());
        $this->assertEquals(\DateTimeImmutable::createFromFormat('U.u', '-0.000001'), (new UuidV1('13813ff6-1dd2-11b2-a456-426655440000'))->getDateTime());
        $this->assertEquals(new \DateTimeImmutable('@-12219292800'), (new UuidV1('00000000-0000-1000-a456-426655440000'))->getDateTime());
    }

    public function testFromStringBase58Padding()
    {
        $this->assertInstanceOf(Uuid::class, Uuid::fromString('111111111u9QRyVM94rdmZ'));
    }

    public function testV1ToV6()
    {
        $uuidV1 = new UuidV1('8189d3de-9670-11ee-b9d1-0242ac120002');
        $uuidV6 = $uuidV1->toV6();

        $this->assertEquals($uuidV1->getDateTime(), $uuidV6->getDateTime());
        $this->assertSame($uuidV1->getNode(), $uuidV6->getNode());
        $this->assertEquals($uuidV6, $uuidV1->toV6());
    }

    public function testV1ToV7BeforeUnixEpochThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert UUID to v7: its timestamp is before the Unix epoch.');

        (new UuidV1('9aba8000-ff00-11b0-b3db-3b3fc83afdfc'))->toV7(); // Timestamp is 1969-01-01 00:00:00.0000000
    }

    public function testV1ToV7()
    {
        $uuidV1 = new UuidV1('eb248d80-ea4f-11ec-9d2a-839425e6fb88');
        $sameUuidV1100NanosecondsLater = new UuidV1('eb248d81-ea4f-11ec-9d2a-839425e6fb88');
        $uuidV7 = $uuidV1->toV7();
        $sameUuidV7100NanosecondsLater = $sameUuidV1100NanosecondsLater->toV7();

        $this->assertSame($uuidV1->getDateTime()->format('Uv'), $uuidV7->getDateTime()->format('Uv'));
        $this->assertEquals($uuidV7, $uuidV1->toV7());
        $this->assertNotEquals($uuidV7, $sameUuidV7100NanosecondsLater);
        $this->assertSame(hexdec('0'.substr($uuidV7, -2)) + 1, hexdec('0'.substr($sameUuidV7100NanosecondsLater, -2)));
    }

    public function testV1ToV7WhenExtraTimeEntropyOverflows()
    {
        $uuidV1 = new UuidV1('10e7718f-2d4f-11be-bfed-cdd35907e584');
        $sameUuidV1100NanosecondsLater = new UuidV1('10e77190-2d4f-11be-bfed-cdd35907e584');
        $uuidV7 = $uuidV1->toV7();
        $sameUuidV7100NanosecondsLater = $sameUuidV1100NanosecondsLater->toV7();

        $this->assertSame($uuidV1->getDateTime()->format('Uv'), $uuidV7->getDateTime()->format('Uv'));
        $this->assertEquals($uuidV7, $uuidV1->toV7());
        $this->assertNotEquals($uuidV7, $sameUuidV7100NanosecondsLater);
        $this->assertSame(hexdec('0'.substr($uuidV7, -2)) + 1, hexdec('0'.substr($sameUuidV7100NanosecondsLater, -2)));
    }

    public function testToString()
    {
        $this->assertSame('a45a8538-77a9-4335-bd30-236f59b81b81', (new UuidV4('a45a8538-77a9-4335-bd30-236f59b81b81'))->toString());
    }
}
