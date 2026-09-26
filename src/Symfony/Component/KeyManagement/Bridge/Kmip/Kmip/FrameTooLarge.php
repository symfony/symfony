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

use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Reports a TTLV item that cannot fit within the bridge's frame limit.
 *
 * Decryption maps an oversized request built from stored ciphertext to an invalid ciphertext error.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class FrameTooLarge extends RuntimeException
{
}
