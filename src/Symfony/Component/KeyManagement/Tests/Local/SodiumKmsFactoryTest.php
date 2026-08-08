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
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\KeyManagement\Local\SodiumKmsFactory;

#[RequiresPhpExtension('sodium')]
class SodiumKmsFactoryTest extends TestCase
{
    public static function provideSupportedSchemes(): iterable
    {
        yield 'memory' => ['sodium://?keys[a]=AAAA'];
        yield 'filesystem' => ['sodium+dir:///tmp'];
    }

    #[DataProvider('provideSupportedSchemes')]
    public function testSupportsBuiltInSchemes(string $dsn)
    {
        $this->assertTrue((new SodiumKmsFactory())->supports(Dsn::fromString($dsn)));
    }

    public function testRejectsForeignSchemes()
    {
        $this->assertFalse((new SodiumKmsFactory())->supports(Dsn::fromString('vault-transit://x@host')));
    }

    public function testFlysystemSchemeIsNotHandledByThisFactory()
    {
        $this->assertFalse((new SodiumKmsFactory())->supports(Dsn::fromString('sodium+fly://service/path')));
    }

    public function testCreateOnUnsupportedSchemeThrows()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new SodiumKmsFactory())->create(Dsn::fromString('vault-transit://x@host'));
    }

    public function testInMemoryDsnRoundTrips()
    {
        $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
        $dsn = 'sodium://?keys[app]='.Base64UrlSafe::encode($key);

        $kms = (new SodiumKmsFactory())->create(Dsn::fromString($dsn));

        $this->assertInstanceOf(SodiumKms::class, $kms);
        $ciphertext = $kms->encrypt('app', 'hello');
        $this->assertSame('hello', $kms->decrypt($ciphertext));
    }

    public function testInMemoryDsnRequiresAtLeastOneKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one key');
        (new SodiumKmsFactory())->create(Dsn::fromString('sodium://'));
    }

    public function testInMemoryDsnRejectsNonBase64()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base64-');
        (new SodiumKmsFactory())->create(Dsn::fromString('sodium://?keys[app]=not-base64$$$'));
    }

    public function testFilesystemDsnReadsKeys()
    {
        $dir = sys_get_temp_dir().'/symfony-kms-factory-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
        file_put_contents($dir.'/app', $key);

        try {
            $kms = (new SodiumKmsFactory())->create(new Dsn(scheme: 'sodium+dir', path: $dir));

            $ciphertext = $kms->encrypt('app', 'hello');
            $this->assertSame('hello', $kms->decrypt($ciphertext));
        } finally {
            unlink($dir.'/app');
            rmdir($dir);
        }
    }

    public function testFilesystemDsnHonoursExtensionOption()
    {
        $dir = sys_get_temp_dir().'/symfony-kms-factory-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
        file_put_contents($dir.'/app.bin', $key);

        try {
            $kms = (new SodiumKmsFactory())->create(new Dsn(scheme: 'sodium+dir', path: $dir, options: ['ext' => '.bin']));

            $this->assertSame('hello', $kms->decrypt($kms->encrypt('app', 'hello')));
        } finally {
            unlink($dir.'/app.bin');
            rmdir($dir);
        }
    }

    public function testFilesystemDsnRequiresPath()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('directory path');
        (new SodiumKmsFactory())->create(Dsn::fromString('sodium+dir://'));
    }
}
