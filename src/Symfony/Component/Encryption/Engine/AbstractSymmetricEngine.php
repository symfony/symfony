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

namespace Symfony\Component\Encryption\Engine;

use Symfony\Component\Encryption\Exception\InvalidArgumentException;
use Symfony\Component\Encryption\Exception\InvalidKeyException;

/**
 * Shared key/nonce length validation for symmetric engines.
 *
 * @author David Gebler <me@davegebler.com>
 *
 * @internal
 */
abstract class AbstractSymmetricEngine implements SymmetricEngineInterface
{
    final protected function assertKeyAndNonce(string $key, string $nonce): void
    {
        $keyLen = \strlen($key);
        if (self::KEY_BYTES !== $keyLen) {
            throw new InvalidKeyException(\sprintf(
                'Key must be exactly %d bytes; got %d.',
                self::KEY_BYTES,
                $keyLen,
            ));
        }

        $nonceLen = \strlen($nonce);
        if (self::NONCE_BYTES !== $nonceLen) {
            throw new InvalidArgumentException(\sprintf(
                'Nonce must be exactly %d bytes; got %d.',
                self::NONCE_BYTES,
                $nonceLen,
            ));
        }
    }
}
