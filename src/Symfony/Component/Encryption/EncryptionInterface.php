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

use Symfony\Component\Encryption\Exception\DecryptionException;
use Symfony\Component\Encryption\Exception\EncryptionException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 6.0
 */
interface EncryptionInterface
{
    /**
     * Generates a new key to be used for encryption.
     *
     * Don't lose your private key and make sure to keep it a secret.
     *
     * @param string|null $secret A secret to be used in symmetric encryption. A
     *                            new secret is generated if none is provided.
     */
    public function generateKey(string $secret = null): KeyInterface;

    /**
     * Gets an encrypted version of the message.
     *
     * Symmetric encryption uses the same key to encrypt and decrypt a message.
     * The key should be kept safe and should not be exposed to the public. Symmetric
     * encryption should be used when you are sending the encrypted message to
     * yourself.
     *
     * Example: You store a value on disk or in a cookie and don't want anyone else
     * to read it.
     *
     * Symmetric encryption is in theory weaker than asymmetric encryption.
     *
     * <code>
     *     $key = $encryption->generateKey();
     *     $ciphertext = $encryption->encrypt('input', $key);
     *     $message = $encryption->decrypt($ciphertext, $key);
     * </code>
     *
     * @param string       $message Plain text version of the message
     * @param KeyInterface $key     A key that holds a string secret
     *
     * @return string formatted as a Symfony Encryption Token
     *
     * @throws EncryptionException
     * @throws InvalidKeyException
     */
    public function encrypt(string $message, KeyInterface $key): string;

    /**
     * Gets an encrypted version of the message that only the recipient can read.
     *
     * Asymmetric encryption uses a "key pair" i.e. a public key and a private key.
     * It is safe to share the public key, but the private key should always be
     * kept a secret.
     *
     * When Alice and Bob want to communicate securely, they share their public keys with
     * each other. Alice will encrypt a message with Bob's public key. When Bob
     * receives the message, he will decrypt it with his private key.
     *
     *
     * <code>
     *     // Bob:
     *     $bobKey = $encryption->generateKey();
     *     $bobPublicOnly = $bobKey->extractPublicKey();
     *     // Bob sends $bobPublicOnly to Alice
     *
     *     // Alice:
     *     $ciphertext = $encryption->encryptFor('input', $bobPublicOnly);
     *     // Alice sends $ciphertext to Bob
     *
     *     // Bob:
     *     $message = $encryption->decrypt($ciphertext, $bobKey);
     * </code>
     *
     * @param string       $message      Plain text version of the message
     * @param KeyInterface $recipientKey Key with a public key of the recipient
     *
     * @return string formatted as a Symfony Encryption Token
     *
     * @throws EncryptionException
     * @throws InvalidKeyException
     */
    public function encryptFor(string $message, KeyInterface $recipientKey): string;

    /**
     * Gets a plain text version of the encrypted message.
     *
     * @param string       $message formatted in the Symfony Encryption Token format
     * @param KeyInterface $key     Key of the recipient, it should contain a private key
     *
     * @throws DecryptionException
     * @throws InvalidKeyException
     */
    public function decrypt(string $message, KeyInterface $key): string;
}
