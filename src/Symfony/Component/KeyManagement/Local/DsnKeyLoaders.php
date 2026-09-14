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

use Symfony\Component\KeyManagement\Base64UrlSafe;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\KeyLoader\FilesystemKeyLoader;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;

/**
 * The two ways every local backend sources its keys from a DSN.
 *
 * Which backend a scheme builds is what tells the local factories apart; where the keys come from
 * is not, since all three read them from the DSN or from a directory in exactly the same way. The
 * messages name the scheme they were given, so each factory keeps speaking its own.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @internal
 */
trait DsnKeyLoaders
{
    /**
     * @throws InvalidArgumentException If the DSN declares no key, or one that is not base64
     */
    private static function inMemoryLoader(#[\SensitiveParameter] Dsn $dsn): InMemoryKeyLoader
    {
        $keys = $dsn->getOption('keys', []);
        if (!\is_array($keys) || !$keys) {
            throw new InvalidArgumentException(\sprintf('The "%s://" DSN must declare at least one key via the "keys[<id>]=<base64>" option.', $dsn->scheme));
        }

        $decoded = [];
        foreach ($keys as $id => $value) {
            if (!\is_string($value)) {
                throw new InvalidArgumentException(\sprintf('Key "%s" in the DSN must be a string.', $id));
            }
            // parse_str() reads "+" as a space, and the decoder would then skip it and lose bytes without a word
            if (preg_match('/\s/', $value)) {
                throw new InvalidArgumentException(\sprintf('Key "%s" in the DSN contains whitespace: inline keys must be base64url-encoded or percent-encoded ("+" as "%%2B").', $id));
            }
            $bytes = Base64UrlSafe::decode($value);
            if (false === $bytes) {
                throw new InvalidArgumentException(\sprintf('Key "%s" in the DSN must be base64- or base64url-encoded.', $id));
            }
            $decoded[$id] = $bytes;
        }

        return new InMemoryKeyLoader($decoded);
    }

    /**
     * @throws InvalidArgumentException If the DSN carries no directory path
     */
    private static function filesystemLoader(#[\SensitiveParameter] Dsn $dsn): FilesystemKeyLoader
    {
        $directory = $dsn->path;
        if ('' === $directory) {
            throw new InvalidArgumentException(\sprintf('The "%s://" DSN must include the directory path (e.g. "%s:///etc/keys").', $dsn->scheme, $dsn->scheme));
        }

        return new FilesystemKeyLoader($directory, $dsn->getOption('ext', ''));
    }
}
