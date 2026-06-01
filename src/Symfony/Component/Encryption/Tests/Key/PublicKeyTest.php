<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Encryption\Tests\Key;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Encoding;
use Symfony\Component\Encryption\Exception\InvalidKeyException;
use Symfony\Component\Encryption\Key\KeyInterface;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

final class PublicKeyTest extends TestCase
{
    public function testHoldsAlgorithmPurposeAndBytes(): void
    {
        $key = PublicKey::fromBytes('x25519', 'encryption', str_repeat("\x01", 32));

        self::assertInstanceOf(KeyInterface::class, $key);
        self::assertSame('x25519', $key->algorithm());
        self::assertSame('encryption', $key->purpose());
        self::assertSame(str_repeat("\x01", 32), $key->bytes());
    }

    public function testExportImportRoundTrip(): void
    {
        $key = PublicKey::fromBytes('x25519', 'encryption', random_bytes(32));

        $imported = PublicKey::import($key->export());

        self::assertSame($key->bytes(), $imported->bytes());
        self::assertSame($key->algorithm(), $imported->algorithm());
        self::assertSame($key->purpose(), $imported->purpose());
    }

    public function testImportRejectsGarbage(): void
    {
        $this->expectException(InvalidKeyException::class);

        PublicKey::import('not a key');
    }

    public function testImportRejectsPrivateKeyMagic(): void
    {
        $private = PrivateKey::fromBytes('x25519', 'encryption', random_bytes(32));

        $this->expectException(InvalidKeyException::class);

        PublicKey::import($private->export());
    }

    public function testImportRejectsUnsupportedVersion(): void
    {
        // SYU = public-key magic; \x02 = unknown version; \x01\x01 = valid algorithm/purpose ids
        $raw = 'SYU' . "\x02\x01\x01" . random_bytes(32);

        $this->expectException(InvalidKeyException::class);

        PublicKey::import(Encoding::toBase64($raw));
    }

    public function testImportRejectsUnknownAlgorithmId(): void
    {
        // \x7f = algorithm id not in ALGORITHM_IDS map
        $raw = 'SYU' . "\x01\x7f\x01" . random_bytes(32);

        $this->expectException(InvalidKeyException::class);

        PublicKey::import(Encoding::toBase64($raw));
    }
}
