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
use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

final class KeyPairTest extends TestCase
{
    public function testExposesPublicAndPrivate(): void
    {
        $sk = random_bytes(32);
        $private = PrivateKey::fromBytes('x25519', 'encryption', $sk);
        $pair = new KeyPair($private->derivePublic(), $private);

        self::assertInstanceOf(PublicKey::class, $pair->public());
        self::assertInstanceOf(PrivateKey::class, $pair->private());
        self::assertSame('x25519', $pair->algorithm());
    }

    public function testExportImportReconstructsBothKeys(): void
    {
        $private = PrivateKey::fromBytes('x25519', 'encryption', random_bytes(32));
        $pair = new KeyPair($private->derivePublic(), $private);

        $imported = KeyPair::import($pair->export());

        self::assertSame($pair->private()->bytes(), $imported->private()->bytes());
        self::assertSame($pair->public()->bytes(), $imported->public()->bytes());
    }
}
