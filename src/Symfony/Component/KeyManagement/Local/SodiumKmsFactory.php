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
 * Builds a {@see SodiumKms} from a DSN. Two schemes are supported:
 *
 *   - `sodium://?keys[id]=BASE64KEY1&keys[id2]=BASE64KEY2`
 *     keys are inlined in the DSN, base64-decoded by the factory.
 *   - `sodium+dir:///etc/keys?ext=.bin`
 *     keys are read from a local directory through {@see \Symfony\Component\KeyManagement\KeyLoader\FilesystemKeyLoader}.
 *
 * Reading keys through Flysystem (S3, FTP, Azure Blob, ...) is provided by
 * `symfony/flysystem-key-management` under the `sodium+fly://` scheme.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class SodiumKmsFactory implements KmsFactoryInterface
{
    use DsnKeyLoaders;

    private const array SCHEMES = ['sodium', 'sodium+dir'];

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->scheme, self::SCHEMES, true);
    }

    public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, self::SCHEMES);
        }

        return new SodiumKms($this->buildKeyLoader($dsn));
    }

    private function buildKeyLoader(Dsn $dsn): KeyLoaderInterface
    {
        return match ($dsn->scheme) {
            'sodium' => self::inMemoryLoader($dsn),
            'sodium+dir' => self::filesystemLoader($dsn),
            default => throw new UnsupportedSchemeException($dsn, self::SCHEMES),
        };
    }
}
