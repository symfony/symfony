<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Flysystem\Tests;

use League\Flysystem\FilesystemReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\KeyManagement\Bridge\Flysystem\FlysystemKmsFactory;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Local\OpenSslKms;
use Symfony\Component\KeyManagement\Local\SealedBoxKms;
use Symfony\Component\KeyManagement\Local\SodiumKms;

class FlysystemKmsFactoryTest extends TestCase
{
    public static function provideSupportedSchemes(): iterable
    {
        yield 'sodium' => ['sodium+fly://service/path'];
        yield 'openssl' => ['openssl+fly://service/path'];
        yield 'sealed-box' => ['sodium-sealed-box+fly://service/path'];
    }

    #[DataProvider('provideSupportedSchemes')]
    public function testSupportsAllFlysystemVariants(string $dsn)
    {
        $factory = new FlysystemKmsFactory(new ServiceLocator([]));

        $this->assertTrue($factory->supports(Dsn::fromString($dsn)));
    }

    public function testRejectsNonFlysystemSchemes()
    {
        $factory = new FlysystemKmsFactory(new ServiceLocator([]));

        $this->assertFalse($factory->supports(Dsn::fromString('sodium://?keys[a]=AAAA')));
        $this->assertFalse($factory->supports(Dsn::fromString('openssl+dir:///tmp')));
        $this->assertFalse($factory->supports(Dsn::fromString('vault-transit://x@host')));
    }

    public function testCreateOnUnsupportedSchemeThrows()
    {
        $this->expectException(UnsupportedSchemeException::class);
        (new FlysystemKmsFactory(new ServiceLocator([])))->create(Dsn::fromString('vault-transit://x@host'));
    }

    #[RequiresPhpExtension('sodium')]
    public function testSodiumFlysystemRoundTrip()
    {
        $key = sodium_crypto_aead_xchacha20poly1305_ietf_keygen();
        $reader = $this->createStub(FilesystemReader::class);
        $reader->method('read')->willReturnCallback(static fn (string $path): string => 'app' === $path ? $key : throw new \LogicException('Unexpected path: '.$path));

        $factory = new FlysystemKmsFactory(new ServiceLocator(['fs' => static fn () => $reader]));

        $kms = $factory->create(Dsn::fromString('sodium+fly://fs'));

        $this->assertInstanceOf(SodiumKms::class, $kms);
        $this->assertSame('hello', $kms->decrypt($kms->encrypt('app', 'hello')));
    }

    #[RequiresPhpExtension('openssl')]
    public function testOpenSslFlysystemRoundTrip()
    {
        $key = random_bytes(32);
        $reader = $this->createStub(FilesystemReader::class);
        $reader->method('read')->willReturnCallback(static fn (string $path): string => 'app' === $path ? $key : throw new \LogicException('Unexpected path: '.$path));

        $factory = new FlysystemKmsFactory(new ServiceLocator(['fs' => static fn () => $reader]));

        $kms = $factory->create(Dsn::fromString('openssl+fly://fs'));

        $this->assertInstanceOf(OpenSslKms::class, $kms);
        $this->assertSame('hello', $kms->decrypt($kms->encrypt('app', 'hello')));
    }

    #[RequiresPhpExtension('sodium')]
    public function testSealedBoxFlysystemRoundTrip()
    {
        $keypair = sodium_crypto_box_keypair();
        $reader = $this->createStub(FilesystemReader::class);
        $reader->method('read')->willReturnCallback(static fn (string $path): string => 'app' === $path ? $keypair : throw new \LogicException('Unexpected path: '.$path));

        $factory = new FlysystemKmsFactory(new ServiceLocator(['fs' => static fn () => $reader]));

        $kms = $factory->create(Dsn::fromString('sodium-sealed-box+fly://fs'));

        $this->assertInstanceOf(SealedBoxKms::class, $kms);
        $this->assertSame('hello', $kms->decrypt($kms->encrypt('app', 'hello')));
    }

    public function testRequiresHost()
    {
        $factory = new FlysystemKmsFactory(new ServiceLocator([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('service id as host');
        $factory->create(Dsn::fromString('sodium+fly:///path'));
    }

    public function testRejectsUnknownService()
    {
        $factory = new FlysystemKmsFactory(new ServiceLocator([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not registered');
        $factory->create(Dsn::fromString('sodium+fly://missing/path'));
    }

    public function testUnknownDsnOptionIsRejected()
    {
        $factory = new FlysystemKmsFactory(new ServiceLocator([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown option "extension"');
        $factory->create(Dsn::fromString('sodium+fly://fs/keys?extension=.bin'));
    }

    public function testArrayDsnOptionIsRejected()
    {
        $factory = new FlysystemKmsFactory(new ServiceLocator([]));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"ext" option');
        $factory->create(Dsn::fromString('sodium+fly://fs/keys?ext[]=.bin'));
    }

    public function testHonoursPathAndExtensionOptions()
    {
        $key = random_bytes(32);
        $captured = null;
        $reader = $this->createStub(FilesystemReader::class);
        $reader->method('read')->willReturnCallback(static function (string $path) use (&$captured, $key): string {
            $captured = $path;

            return $key;
        });

        $factory = new FlysystemKmsFactory(new ServiceLocator(['fs' => static fn () => $reader]));

        $kms = $factory->create(Dsn::fromString('openssl+fly://fs/keys?ext=.bin'));
        $kms->encrypt('app', 'hello');

        $this->assertSame('keys/app.bin', $captured);
    }
}
