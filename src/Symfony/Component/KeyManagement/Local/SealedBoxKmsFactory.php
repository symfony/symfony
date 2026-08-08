<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Local;

use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;
use Symfony\Component\KeyManagement\KeyLoader\KeyLoaderInterface;

/**
 * Builds a {@see SealedBoxKms} from a DSN. Two schemes are supported:
 *
 *   - `sodium-sealed-box://?keys[id]=BASE64KEY1`  (in-memory keys)
 *   - `sodium-sealed-box+dir:///etc/keys?ext=.bin`  (filesystem keys)
 *
 * Each entry must be either a 32-byte public key or a 64-byte keypair
 * encoded in base64 (in-memory) or written as raw bytes (filesystem).
 * See {@see SealedBoxKms} for the encrypt-only / full-mode semantics.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class SealedBoxKmsFactory implements KmsFactoryInterface
{
    use DsnKeyLoaders;

    private const array SCHEMES = ['sodium-sealed-box', 'sodium-sealed-box+dir'];

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->scheme, self::SCHEMES, true);
    }

    public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, self::SCHEMES);
        }

        return new SealedBoxKms($this->buildKeyLoader($dsn));
    }

    private function buildKeyLoader(Dsn $dsn): KeyLoaderInterface
    {
        return match ($dsn->scheme) {
            'sodium-sealed-box' => self::inMemoryLoader($dsn),
            'sodium-sealed-box+dir' => self::filesystemLoader($dsn),
            default => throw new UnsupportedSchemeException($dsn, self::SCHEMES),
        };
    }
}
