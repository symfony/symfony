<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Test;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;

/**
 * No-crypto, in-memory implementation of {@see EncrypterInterface} /
 * {@see DecrypterInterface} for tests. The `$blob` of returned ciphertexts
 * is the plaintext prefixed with a marker that embeds the key id and the
 * AAD (`encrypted/<keyId>/<hex(aad)>/<plaintext>`) so that:
 *
 *   - tests cannot accidentally compare a plaintext to a ciphertext;
 *   - decrypting something that was never produced by encrypt() fails with
 *     {@see DecryptionFailedException}, catching mistakes such as feeding a
 *     plaintext back into decrypt();
 *   - decrypting under a different key id or with a different AAD than was
 *     used at encryption time fails the prefix check, catching key-routing
 *     and AAD-binding bugs in tests.
 *
 * The `$deterministic` flag is accepted for interface compliance but has no
 * observable effect: this fixture is no-crypto and the produced blob is
 * already a pure function of `(keyId, aad, plaintext)`.
 *
 * MUST NOT be used outside test fixtures.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class InMemoryKms implements DecrypterInterface, EncrypterInterface, DataKeyGeneratorInterface
{
    public const string CIPHERTEXT_PREFIX = 'encrypted/';

    public int $calls = 0;

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        ++$this->calls;

        return new Ciphertext(self::CIPHERTEXT_PREFIX.$keyId.'/'.bin2hex($aad).'/'.$plaintext, $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        ++$this->calls;

        $expected = self::CIPHERTEXT_PREFIX.$ciphertext->keyId.'/'.bin2hex($aad).'/';
        if (!str_starts_with($ciphertext->blob, $expected)) {
            throw new DecryptionFailedException();
        }

        return substr($ciphertext->blob, \strlen($expected));
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        ++$this->calls;
        $plaintext = str_repeat("\0", $length);

        return new DataKey($plaintext, $this->encrypt($keyId, $plaintext, $aad));
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return new DataKey($this->decrypt($wrapped, $aad), $wrapped);
    }
}
