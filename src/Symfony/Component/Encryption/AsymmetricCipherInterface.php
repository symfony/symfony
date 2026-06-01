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

namespace Symfony\Component\Encryption;

use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

/**
 * Public-key (asymmetric) encryption.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
interface AsymmetricCipherInterface
{
    public function generateKeyPair(string $algorithm = 'x25519', int $rsaKeyBits = 3072): KeyPair;

    public function encryptAnonymous(string $plaintext, PublicKey $recipient): string;

    public function decryptAnonymous(string $ciphertext, KeyPair $recipient): string;

    public function encryptAuthenticated(string $plaintext, PrivateKey $senderPrivate, PublicKey $recipient): string;

    public function decryptAuthenticated(string $ciphertext, KeyPair $recipient, PublicKey $senderPublic): string;
}
