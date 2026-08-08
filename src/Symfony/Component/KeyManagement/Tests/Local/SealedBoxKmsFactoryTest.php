<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Local;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Local\SealedBoxKms;
use Symfony\Component\KeyManagement\Local\SealedBoxKmsFactory;

#[RequiresPhpExtension('sodium')]
class SealedBoxKmsFactoryTest extends TestCase
{
    public static function provideSupportedSchemes(): iterable
    {
        yield 'memory' => ['sodium-sealed-box://?keys[a]=AAAA'];
        yield 'filesystem' => ['sodium-sealed-box+dir:///tmp'];
    }

    #[DataProvider('provideSupportedSchemes')]
    public function testSupportsBuiltInSchemes(string $dsn)
    {
        $this->assertTrue((new SealedBoxKmsFactory())->supports(Dsn::fromString($dsn)));
    }

    public function testRejectsForeignSchemes()
    {
        $this->assertFalse((new SealedBoxKmsFactory())->supports(Dsn::fromString('sodium://?keys[a]=AAAA')));
    }

    public function testCreateOnUnsupportedSchemeThrows()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new SealedBoxKmsFactory())->create(Dsn::fromString('sodium://?keys[a]=AAAA'));
    }

    public function testInMemoryDsnRoundTripsWithKeypair()
    {
        $keypair = sodium_crypto_box_keypair();
        $dsn = 'sodium-sealed-box://?keys[app]='.Base64UrlSafe::encode($keypair);

        $kms = (new SealedBoxKmsFactory())->create(Dsn::fromString($dsn));

        $this->assertInstanceOf(SealedBoxKms::class, $kms);
        $ciphertext = $kms->encrypt('app', 'hello');
        $this->assertSame('hello', $kms->decrypt($ciphertext));
    }

    public function testInMemoryDsnSupportsPublicKeyOnlyEntries()
    {
        $keypair = sodium_crypto_box_keypair();
        $publicKey = sodium_crypto_box_publickey($keypair);

        $writerDsn = 'sodium-sealed-box://?keys[app]='.Base64UrlSafe::encode($publicKey);
        $writer = (new SealedBoxKmsFactory())->create(Dsn::fromString($writerDsn));

        $readerDsn = 'sodium-sealed-box://?keys[app]='.Base64UrlSafe::encode($keypair);
        $reader = (new SealedBoxKmsFactory())->create(Dsn::fromString($readerDsn));

        $this->assertSame('hello', $reader->decrypt($writer->encrypt('app', 'hello')));
    }

    public function testInMemoryDsnRequiresAtLeastOneKey()
    {
        $this->expectException(InvalidArgumentException::class);
        (new SealedBoxKmsFactory())->create(Dsn::fromString('sodium-sealed-box://'));
    }

    public function testFilesystemDsnRequiresPath()
    {
        $this->expectException(InvalidArgumentException::class);
        (new SealedBoxKmsFactory())->create(Dsn::fromString('sodium-sealed-box+dir://'));
    }
}
