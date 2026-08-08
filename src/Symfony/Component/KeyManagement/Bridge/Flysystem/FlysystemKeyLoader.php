<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Flysystem;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;
use League\Flysystem\UnableToReadFile;
use Symfony\Component\KeyManagement\Exception\InvalidArgumentException;
use Symfony\Component\KeyManagement\Exception\KeyNotFoundException;
use Symfony\Component\KeyManagement\Exception\RuntimeException;
use Symfony\Component\KeyManagement\KeyLoader\KeyLoaderInterface;

/**
 * Reads each key from a Flysystem-backed storage at
 * `<directory>/<keyId><extension>`.
 *
 * Useful when keys live in remote stores (S3, FTP/SFTP, Azure Blob, Google
 * Cloud Storage, ...). The user wires a Flysystem instance separately
 * (typically through `league/flysystem-bundle`) and passes it in.
 *
 * The key material is returned byte-for-byte, exactly like
 * {@see \Symfony\Component\KeyManagement\KeyLoader\FilesystemKeyLoader},
 * so raw binary keys are never altered.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
final class FlysystemKeyLoader implements KeyLoaderInterface
{
    public function __construct(
        private readonly FilesystemReader $flysystem,
        private readonly string $directory = '',
        private readonly string $extension = '',
    ) {
    }

    public function load(string $keyId): string
    {
        foreach (explode('/', $keyId) as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment || str_contains($segment, "\0")) {
                throw new InvalidArgumentException(\sprintf('Invalid key id "%s".', $keyId));
            }
        }

        $path = ltrim($this->directory.'/'.$keyId.$this->extension, '/');

        try {
            return $this->flysystem->read($path);
        } catch (UnableToReadFile $e) {
            throw new KeyNotFoundException($keyId, $e);
        } catch (FilesystemException $e) {
            throw new RuntimeException(\sprintf('Failed to read key material for "%s".', $keyId), 0, $e);
        }
    }
}
