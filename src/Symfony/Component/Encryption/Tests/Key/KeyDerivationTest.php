<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption\Tests\Key;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Encryption\Engine\SymmetricEngineInterface;
use Symfony\Component\Encryption\Envelope\Envelope;
use Symfony\Component\Encryption\Key\KeyDerivation;

final class KeyDerivationTest extends TestCase
{
    public function testPbkdf2IsDeterministicAnd32Bytes()
    {
        $salt = str_repeat("\x01", Envelope::SALT_BYTES);

        $a = KeyDerivation::derivePbkdf2('password', $salt, 10000);
        $b = KeyDerivation::derivePbkdf2('password', $salt, 10000);

        self::assertSame(SymmetricEngineInterface::KEY_BYTES, \strlen($a));
        self::assertSame($a, $b);
    }

    public function testPbkdf2MatchesPhpReference()
    {
        $salt = random_bytes(Envelope::SALT_BYTES);

        self::assertSame(
            hash_pbkdf2('sha256', 'pw', $salt, 12345, SymmetricEngineInterface::KEY_BYTES, true),
            KeyDerivation::derivePbkdf2('pw', $salt, 12345),
        );
    }

    public function testPbkdf2DiffersByPassword()
    {
        $salt = random_bytes(Envelope::SALT_BYTES);

        self::assertNotSame(
            KeyDerivation::derivePbkdf2('one', $salt, 10000),
            KeyDerivation::derivePbkdf2('two', $salt, 10000),
        );
    }

    public function testArgon2idIsDeterministicAnd32Bytes()
    {
        if (!\function_exists('sodium_crypto_pwhash')) {
            self::markTestSkipped('ext-sodium is required for Argon2id.');
        }

        $salt = random_bytes(Envelope::SALT_BYTES);
        $ops = \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
        $mem = \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;

        $a = KeyDerivation::deriveArgon2id('password', $salt, $ops, $mem);
        $b = KeyDerivation::deriveArgon2id('password', $salt, $ops, $mem);

        self::assertSame(SymmetricEngineInterface::KEY_BYTES, \strlen($a));
        self::assertSame($a, $b);
    }

    public function testPreferredKdfIsArgon2idWhenSodiumPresent()
    {
        $expected = \function_exists('sodium_crypto_pwhash')
            ? Envelope::KDF_ARGON2ID
            : Envelope::KDF_PBKDF2_SHA256;

        self::assertSame($expected, KeyDerivation::preferredKdfId());
    }
}
