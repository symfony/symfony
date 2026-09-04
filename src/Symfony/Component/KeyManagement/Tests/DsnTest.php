<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;

class DsnTest extends TestCase
{
    public function testFullDsn()
    {
        $dsn = Dsn::fromString('vault-transit://AKIA:s%2Fcret@vault.example.com:8200/v1/?mount=transit&namespace=tenant-a');

        $this->assertSame('vault-transit', $dsn->scheme);
        $this->assertSame('vault.example.com', $dsn->host);
        $this->assertSame('AKIA', $dsn->user);
        $this->assertSame('s/cret', $dsn->password);
        $this->assertSame(8200, $dsn->port);
        $this->assertSame('/v1/', $dsn->path);
        $this->assertSame('transit', $dsn->getOption('mount'));
        $this->assertSame('tenant-a', $dsn->getOption('namespace'));
    }

    public function testHostlessDsn()
    {
        $dsn = Dsn::fromString('sodium://?keys[app]=AAAA');

        $this->assertSame('sodium', $dsn->scheme);
        $this->assertNull($dsn->host);
        $this->assertSame(['app' => 'AAAA'], $dsn->getOption('keys'));
    }

    public function testPathOnlyDsn()
    {
        $dsn = Dsn::fromString('sodium+dir:///etc/keys?ext=.bin');

        $this->assertSame('sodium+dir', $dsn->scheme);
        $this->assertNull($dsn->host);
        $this->assertSame('/etc/keys', $dsn->path);
        $this->assertSame('.bin', $dsn->getOption('ext'));
    }

    public function testGetOptionDefaults()
    {
        $dsn = Dsn::fromString('sodium://?a=1');

        $this->assertSame('1', $dsn->getOption('a'));
        $this->assertNull($dsn->getOption('missing'));
        $this->assertSame('fallback', $dsn->getOption('missing', 'fallback'));
    }

    public function testGetRequiredOptionThrowsWhenMissing()
    {
        $dsn = Dsn::fromString('sodium://?a=1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Required DSN option "b"');
        $dsn->getRequiredOption('b');
    }

    public function testMissingScheme()
    {
        $this->expectException(InvalidArgumentException::class);
        Dsn::fromString('not-a-dsn');
    }

    public function testInvalidDsn()
    {
        $this->expectException(InvalidArgumentException::class);
        Dsn::fromString('http://:invalid');
    }
}
