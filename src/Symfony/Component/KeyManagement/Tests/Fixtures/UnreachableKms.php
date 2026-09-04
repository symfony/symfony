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

/**
 * Backend that is down, whatever it is asked.
 *
 * Used to exercise what a caller does with the failure, and above all what the raised stack trace
 * carries: a secret reaches one in the first place because a backend threw while holding it.
 */
final class UnreachableKms implements DataKeyGeneratorInterface, DecrypterInterface, EncrypterInterface
{
    public function encrypt(string $keyId, #[\SensitiveParameter] string $plaintext, string $aad = '', bool $deterministic = false): Ciphertext
    {
        throw new \RuntimeException('The backend is down.');
    }

    public function decrypt(Ciphertext $ciphertext, string $aad = ''): string
    {
        throw new \RuntimeException('The backend is down.');
    }

    public function generateDataKey(string $keyId, int $length = 32, string $aad = ''): DataKey
    {
        throw new \RuntimeException('The backend is down.');
    }

    public function unwrapDataKey(Ciphertext $wrapped, string $aad = ''): DataKey
    {
        throw new \RuntimeException('The backend is down.');
    }
}
