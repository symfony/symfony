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

        $this->assertSame(0, strncmp($a, $b, 20));
        $a = base_convert(strtr(substr($a, -6), 'ABCDEFGHJKMNPQRSTVWXYZ', 'abcdefghijklmnopqrstuv'), 32, 10);
        $b = base_convert(strtr(substr($b, -6), 'ABCDEFGHJKMNPQRSTVWXYZ', 'abcdefghijklmnopqrstuv'), 32, 10);
        $this->assertSame(1, $b - $a);
    }

    public function testWithInvalidUlid()
    {
        $this->expectException(\InvalidArgumentException::class);
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
    public function testGetTime()
    {
        $time = microtime(false);
        $ulid = new Ulid();
        $time = substr($time, 11).substr($time, 1, 4);

        $this->assertSame((float) $time, $ulid->getTime());
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
}
