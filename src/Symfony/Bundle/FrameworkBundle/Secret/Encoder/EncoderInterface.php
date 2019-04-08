<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Secret\Encoder;

/**
 * EncoderInterface defines an interface to encrypt and decrypt secrets.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface EncoderInterface
{
    /**
     * Generate the keys and material necessary for its operation.
     *
     * @param bool $override Override previous keys if already exists
     *
     * @return string[] List of resources created
     */
    public function generateKeys(bool $override = false): array;

    /**
     * Encrypt a secret.
     */
    public function encrypt(string $secret): string;

    /**
     * Decrypt a secret.
     */
    public function decrypt(string $encryptedSecret): string;
}
