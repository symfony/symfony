<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Vault\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Bridge\Vault\TransitKms;
use Symfony\Component\KeyManagement\Bridge\Vault\TransitKmsFactory;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;

class TransitKmsFactoryTest extends TestCase
{
    public function testSupportsTheVaultScheme()
    {
        $this->assertTrue((new TransitKmsFactory())->supports(Dsn::fromString('vault-transit://t@vault.example.com')));
    }

    public function testRejectsForeignSchemes()
    {
        $this->assertFalse((new TransitKmsFactory())->supports(Dsn::fromString('sodium://?keys[a]=AAAA')));
    }

    public function testCreateOnUnsupportedSchemeThrows()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new TransitKmsFactory())->create(Dsn::fromString('sodium://?keys[a]=AAAA'));
    }

    public function testCreateBuildsTransitKms()
    {
        $kms = (new TransitKmsFactory())->create(Dsn::fromString('vault-transit://t@vault.example.com:8200/v1/?mount=transit&namespace=tenant-a'));

        $this->assertInstanceOf(TransitKms::class, $kms);
    }

    public function testSchemeOptionAcceptsHttpForLocalDevSandboxes()
    {
        $kms = (new TransitKmsFactory())->create(Dsn::fromString('vault-transit://t@127.0.0.1:8200/v1/?scheme=http'));

        $this->assertInstanceOf(TransitKms::class, $kms);
    }

    public function testSchemeOptionRejectsArbitraryValues()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"scheme" option');
        (new TransitKmsFactory())->create(Dsn::fromString('vault-transit://t@vault.local/v1/?scheme=ftp'));
    }

    public function testUnknownDsnOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option "mont"');
        (new TransitKmsFactory())->create(Dsn::fromString('vault-transit://t@vault.local/v1/?mont=transit'));
    }

    public function testArrayDsnOptionIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"mount" option');
        (new TransitKmsFactory())->create(Dsn::fromString('vault-transit://t@vault.local/v1/?mount[]=transit'));
    }

    public function testHostIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vault host');
        (new TransitKmsFactory())->create(Dsn::fromString('vault-transit:///v1/'));
    }

    public function testTokenIsRequired()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Vault token');
        (new TransitKmsFactory())->create(Dsn::fromString('vault-transit://vault.example.com'));
    }
}
