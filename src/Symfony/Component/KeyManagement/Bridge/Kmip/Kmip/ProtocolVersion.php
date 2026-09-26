<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Kmip;

/**
 * KMIP versions supported by the bridge's authenticated encryption operations.
 *
 * KMIP 1.0 and 1.1 do not define Encrypt or Decrypt, so they cannot perform the bridge's server-side key operations.
 * KMIP 1.2 and 1.3 define those operations but not the Authenticated Encryption Tag or Authenticated Encryption Additional Data payload fields introduced in 1.4.
 * The tag lets Decrypt detect changed ciphertext. The additional data field carries the caller's AAD so that it is authenticated without being encrypted. Without these fields, the bridge cannot verify its authenticated ciphertext or reject decryption with different AAD.
 *
 * The bridge supports its single-part Encrypt and Decrypt flow on 1.4 and 2.0.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
enum ProtocolVersion: string
{
    case Version14 = '1.4';
    case Version20 = '2.0';
}
