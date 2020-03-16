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
use Symfony\Component\Uid\UidFactory;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\UuidV1;
use Symfony\Component\Uid\UuidV3;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Uid\UuidV5;
use Symfony\Component\Uid\UuidV6;

class UidFactoryTest extends TestCase
{
    public function testDefaults()
    {
        $factory = new UidFactory();

        $this->assertInstanceOf(Ulid::class, $factory->ulid());
        $this->assertInstanceOf(UuidV1::class, $factory->uuidV1());
        $this->assertInstanceOf(UuidV3::class, $factory->uuidV3($factory->uuidV1(), 'foo'));
        $this->assertInstanceOf(UuidV4::class, $factory->uuidV4());
        $this->assertInstanceOf(UuidV5::class, $factory->uuidV5($factory->uuidV1(), 'foo'));
        $this->assertInstanceOf(UuidV6::class, $factory->uuidV6());
    }

    public function testV1()
    {
        $v1 = 'caa9884e-105e-11b6-8101-010101010101';
        $entropySource = function (int $bytes) use (&$called) {
            $this->assertSame(8, $bytes);

            return "\1\1\1\1\1\1\1\1";
        };
        $factory = new UidFactory($entropySource);
        $uuid = (string) $factory->uuidV1();
        $this->assertSame('-8101-010101010101', substr($uuid, -18));
        $this->assertNotSame($v1, $uuid);

        $timeSource = function () {
            return '1111111112345678';
        };
        $factory = new UidFactory(null, $timeSource);
        $uuid = (string) $factory->uuidV1();
        $this->assertSame('caa9884e-105e-11b6', substr($uuid, 0, 18));
        $this->assertNotSame($v1, $uuid);

        $factory = new UidFactory($entropySource, $timeSource);
        $this->assertSame($v1, (string) $factory->uuidV1());
    }

    public function testV6()
    {
        $v6 = '1b6105ec-aa98-684e-8102-030405060708';
        $entropySource = function (int $bytes) use (&$called) {
            $this->assertSame(8, $bytes);

            return "\1\2\3\4\5\6\7\x08";
        };
        $factory = new UidFactory($entropySource);
        $uuid = (string) $factory->uuidV6();
        $this->assertSame('-8102-030405060708', substr($uuid, -18));
        $this->assertNotSame($v6, $uuid);

        $timeSource = function () {
            return '1111111112345678';
        };
        $factory = new UidFactory(null, $timeSource);
        $uuid = (string) $factory->uuidV6();
        $this->assertSame('1b6105ec-aa98-684e', substr($uuid, 0, 18));
        $this->assertNotSame($v6, $uuid);

        $factory = new UidFactory($entropySource, $timeSource);
        $this->assertSame($v6, (string) $factory->uuidV6());
    }

    public function testUlid()
    {
        $randomSource = function (int $bytes) use (&$called) {
            $this->assertSame(10, $bytes);

            return "\0\1\2\3\4\5\6\7\x08\0";
        };
        $timeSource = function () {
            return '1111111111112345678';
        };
        $factory = new UidFactory(null, $timeSource, $randomSource);

        $this->assertSame('351R94XWJ20001G0G3010501G7', (string) $factory->ulid());
        $this->assertSame('351R94XWJ20001G0G3010501G8', (string) $factory->ulid());

        $timeSource = function () {
            return '1011111111112345678';
        };
        $factory = new UidFactory(null, $timeSource, $randomSource);
        $this->assertSame('2VYQ1XRMJ20001G0G3010501G9', (string) $factory->ulid());
    }

    public function testV4()
    {
        $randomSource = function (int $bytes) use (&$called) {
            $this->assertSame(16, $bytes);

            return "\0\1\2\3\4\5\6\7\x08\x70\x60\x50\x40\x30\x20\x10";
        };
        $factory = new UidFactory(null, null, $randomSource);

        $this->assertSame('00010203-0405-4607-8870-605040302010', (string) $factory->uuidV4());
    }
}
