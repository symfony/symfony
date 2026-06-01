<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Key\SecretKey;

/**
 * Authenticated symmetric encryption.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
interface SymmetricCipherInterface
{
    public function generateKey(): SecretKey;

    public function encrypt(string $plaintext, SecretKey $key): string;

    public function decrypt(string $ciphertext, SecretKey $key): string;

    public function encryptWithPassword(string $plaintext, #[\SensitiveParameter] string $password): string;

    public function decryptWithPassword(string $ciphertext, #[\SensitiveParameter] string $password): string;
}
