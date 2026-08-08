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
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\EncrypterInterface;

/**
 * Backend that supports encrypt/decrypt but NOT data-key generation.
 *
 * Used to exercise the capability check in
 * {@see \Symfony\Component\KeyManagement\Command\CommandTrait::resolveDataKeyGenerator()}.
 */
final class EncryptOnlyKms implements EncrypterInterface, DecrypterInterface
{
    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        return new Ciphertext($plaintext, $keyId);
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        return $ciphertext->blob;
    }
}
