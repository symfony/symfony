<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Uid;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class UuidTest extends TestCase
{
    private const A_UUID_V1 = 'd9e7a184-5d5b-11ea-a62a-3499710062d0';
    private const A_UUID_V4 = 'd6b3345b-2905-4048-a83c-b5988e765d98';

    public function testConstructorWithInvalidUuid()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid UUID: "this is not a uuid".');

        new Uuid('this is not a uuid');
    }

    public function testConstructorWithValidUuid()
    {
        $uuid = new Uuid(self::A_UUID_V4);

        $this->assertSame(self::A_UUID_V4, (string) $uuid);
        $this->assertSame('"'.self::A_UUID_V4.'"', json_encode($uuid));
    }

    public function testV1()
    {
        $uuid = Uuid::v1();

        $this->assertSame(Uuid::TYPE_1, $uuid->getType());
    }

    public function testV3()
    {
        $uuid = Uuid::v3(new Uuid(self::A_UUID_V4), 'the name');

        $this->assertSame(Uuid::TYPE_3, $uuid->getType());
    }

    public function testV4()
    {
        $uuid = Uuid::v4();

        $this->assertSame(Uuid::TYPE_4, $uuid->getType());
    }

    public function testV5()
    {
        $uuid = Uuid::v5(new Uuid(self::A_UUID_V4), 'the name');

        $this->assertSame(Uuid::TYPE_5, $uuid->getType());
    }

    public function testBinary()
    {
        $uuid = new Uuid(self::A_UUID_V4);

        $this->assertSame(self::A_UUID_V4, (string) Uuid::fromBinary($uuid->toBinary()));
    }

    public function testIsValid()
    {
        $this->assertFalse(Uuid::isValid('not a uuid'));
        $this->assertTrue(Uuid::isValid(self::A_UUID_V4));
    }

    public function testIsNull()
    {
        $uuid = new Uuid(self::A_UUID_V1);
        $this->assertFalse($uuid->isNull());

        $uuid = new Uuid('00000000-0000-0000-0000-000000000000');
        $this->assertTrue($uuid->isNull());
    }

    public function testEquals()
    {
        $uuid1 = new Uuid(self::A_UUID_V1);
        $uuid2 = new Uuid(self::A_UUID_V4);

        $this->assertTrue($uuid1->equals($uuid1));
        $this->assertFalse($uuid1->equals($uuid2));
    }

    /**
     * @dataProvider provideInvalidEqualType
     */
    public function testEqualsAgainstOtherType($other)
    {
        $this->assertFalse((new Uuid(self::A_UUID_V4))->equals($other));
    }

    public function provideInvalidEqualType()
    {
        yield [null];
        yield [self::A_UUID_V1];
        yield [self::A_UUID_V4];
        yield [new \stdClass()];
    }

    public function testCompare()
    {
        $uuids = [];

        $uuids[] = $b = new Uuid('00000000-0000-0000-0000-00000000000b');
        $uuids[] = $a = new Uuid('00000000-0000-0000-0000-00000000000a');
        $uuids[] = $d = new Uuid('00000000-0000-0000-0000-00000000000d');
        $uuids[] = $c = new Uuid('00000000-0000-0000-0000-00000000000c');

        $this->assertNotSame([$a, $b, $c, $d], $uuids);

        usort($uuids, static function (Uuid $a, Uuid $b): int {
            return $a->compare($b);
        });

        $this->assertSame([$a, $b, $c, $d], $uuids);
    }

    public function testExtraMethods()
    {
        $uuid = new Uuid(self::A_UUID_V1);

        $this->assertSame(1583245966.746458, $uuid->getTime());
        $this->assertSame('3499710062d0', $uuid->getMac());
        $this->assertSame(self::A_UUID_V1, (string) $uuid);
    }
}
