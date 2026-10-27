<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Fixtures;

use Symfony\Component\KeyManagement\Ciphertext;
use Symfony\Component\KeyManagement\DataKey;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\DecryptionFailedException;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

/**
 * Backend that wraps a data key and encrypts a payload through two operations, as Azure Key Vault does.
 *
 * Each operation only reads back what its counterpart wrote: {@see unwrapDataKey()} refuses a
 * ciphertext of {@see encrypt()}, and {@see decrypt()} a wrapping of {@see wrapDataKey()}.
 */
final class TwoOperationsKms implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface
{
    public function __construct(
        private readonly InMemoryKms $inner,
    ) {
    }

    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = ''): Ciphertext
    {
        return new Ciphertext('encrypt:'.$this->inner->encrypt($keyId, $plaintext, $aad)->blob, $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        return $this->inner->decrypt(self::strip('encrypt:', $ciphertext), $aad);
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        return $this->inner->generateDataKey($keyId, $length, $aad)->use(fn (#[\SensitiveParameter] string $plaintext): DataKey => new DataKey($plaintext, $this->wrapDataKey($keyId, $plaintext, $aad)));
    }

    public function wrapDataKey(string $keyId, #[\SensitiveParameter] string $dataKey, string $aad = ''): Ciphertext
    {
        return new Ciphertext('wrap:'.$this->inner->encrypt($keyId, $dataKey, $aad)->blob, $keyId);
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        return $this->inner->unwrapDataKey(self::strip('wrap:', $wrapped), $aad);
    }

    private static function strip(string $operation, Ciphertext $ciphertext): Ciphertext
    {
        if (!str_starts_with($ciphertext->blob, $operation)) {
            throw new DecryptionFailedException();
        }

        return new Ciphertext(substr($ciphertext->blob, \strlen($operation)), $ciphertext->keyId);
    }
}
