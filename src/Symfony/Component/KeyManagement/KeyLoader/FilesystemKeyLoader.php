<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\KeyLoader;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\LogicException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Reads each key from a file `<directory>/<keyId><extension>`.
 *
 * The file content is taken verbatim as the raw key material: no trimming, no
 * decoding. Generate keys with primitives that emit exact byte counts and
 * no trailing newline, e.g. `head -c 32 /dev/urandom > key.bin` or
 * `file_put_contents('key.bin', sodium_crypto_aead_xchacha20poly1305_ietf_keygen())`.
 * Bad-length material surfaces as an InvalidArgumentException at first use.
 *
 * Subdirectories under `keyId` are allowed (e.g. `tenant-a/master`); any
 * id that escapes `$directory` after canonicalization is rejected.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class FilesystemKeyLoader implements KeyLoaderInterface
{
    private readonly Filesystem $filesystem;
    private readonly string $directory;

    public function __construct(
        string $directory,
        private readonly string $extension = '',
        ?Filesystem $filesystem = null,
    ) {
        if (!class_exists(Filesystem::class)) {
            throw new LogicException('The "symfony/filesystem" package is required to use the FilesystemKeyLoader. Try running "composer require symfony/filesystem".');
        }

        $this->directory = Path::canonicalize($directory);
        $this->filesystem = $filesystem ?? new Filesystem();
    }

    public function load(string $keyId): string
    {
        if (str_contains($keyId, "\0")) {
            throw new InvalidArgumentException(\sprintf('Invalid key id "%s".', $keyId));
        }

        $resolved = Path::canonicalize($this->directory.'/'.$keyId.$this->extension);
        if (!Path::isBasePath($this->directory, $resolved)) {
            throw new InvalidArgumentException(\sprintf('Invalid key id "%s".', $keyId));
        }

        if (!$this->filesystem->exists($resolved)) {
            throw new KeyNotFoundException($keyId);
        }

        try {
            return $this->filesystem->readFile($resolved);
        } catch (IOException $e) {
            throw new RuntimeException(\sprintf('Failed to read key material for "%s".', $keyId), 0, $e);
        }
    }
}
