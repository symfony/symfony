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

use Symfony\Component\Encryption\Key\KeyPair;
use Symfony\Component\Encryption\Key\PrivateKey;
use Symfony\Component\Encryption\Key\PublicKey;

/**
 * Digital signatures (Ed25519, RSA, and ECDSA P-256).
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @api
 */
interface SignerInterface
{
    public function generateKeyPair(string $algorithm = 'ed25519'): KeyPair;

    public function signDetached(string $message, PrivateKey $key): string;

    public function verifyDetached(string $signature, string $message, PublicKey $key): bool;

    public function signAttached(string $message, PrivateKey $key): string;

    public function openAttached(string $signedMessage, PublicKey $key): string;
}
