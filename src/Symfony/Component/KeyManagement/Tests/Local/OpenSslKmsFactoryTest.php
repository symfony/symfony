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
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Local\OpenSslKmsFactory;

#[RequiresPhpExtension('openssl')]
class OpenSslKmsFactoryTest extends TestCase
{
    public static function provideSupportedSchemes(): iterable
    {
        yield 'memory' => ['openssl://?keys[a]=AAAA'];
        yield 'filesystem' => ['openssl+dir:///tmp'];
    }

    #[DataProvider('provideSupportedSchemes')]
    public function testSupportsBuiltInSchemes(string $dsn)
    {
        $this->assertTrue((new OpenSslKmsFactory())->supports(Dsn::fromString($dsn)));
    }

    public function testRejectsForeignSchemes()
    {
        $this->assertFalse((new OpenSslKmsFactory())->supports(Dsn::fromString('sodium://?keys[a]=AAAA')));
    }

    public function testCreateOnUnsupportedSchemeThrows()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new OpenSslKmsFactory())->create(Dsn::fromString('vault-transit://x@host'));
    }

    public function testInMemoryDsnRoundTrips()
    {
        $key = random_bytes(32);
        $dsn = 'openssl://?keys[app]='.Base64UrlSafe::encode($key);

        $kms = (new OpenSslKmsFactory())->create(Dsn::fromString($dsn));

        $this->assertInstanceOf(OpenSslKms::class, $kms);
        $ciphertext = $kms->encrypt('app', 'hello');
        $this->assertSame('hello', $kms->decrypt($ciphertext));
    }

    public function testInMemoryDsnAcceptsBase64UrlWithoutPadding()
    {
        $key = str_repeat("\xFE", 32);
        $base64Url = Base64UrlSafe::encode($key);

        $kms = (new OpenSslKmsFactory())->create(Dsn::fromString('openssl://?keys[app]='.$base64Url));

        $this->assertSame('hello', $kms->decrypt($kms->encrypt('app', 'hello')));
    }

    public function testInMemoryDsnRequiresAtLeastOneKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one key');
        (new OpenSslKmsFactory())->create(Dsn::fromString('openssl://'));
    }

    public function testInMemoryDsnRejectsNonBase64()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('base64-');
        (new OpenSslKmsFactory())->create(Dsn::fromString('openssl://?keys[app]=not-base64$$$'));
    }

    public function testFilesystemDsnReadsKeys()
    {
        $dir = sys_get_temp_dir().'/symfony-kms-factory-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/app', random_bytes(32));

        try {
            $kms = (new OpenSslKmsFactory())->create(new Dsn(scheme: 'openssl+dir', path: $dir));

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
        file_put_contents($dir.'/app.bin', random_bytes(32));

        try {
            $kms = (new OpenSslKmsFactory())->create(new Dsn(scheme: 'openssl+dir', path: $dir, options: ['ext' => '.bin']));

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
        (new OpenSslKmsFactory())->create(Dsn::fromString('openssl+dir://'));
    }
}
