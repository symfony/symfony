<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * ValidatorConfigCache uses instances of MetadataValidatorInterface
 * to check whether cached data is still fresh.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ValidatorConfigCache implements ConfigCacheInterface
{
    /**
     * @var string
     */
    private $file;

    /**
     * @var MetadataValidatorInterface[]
     */
    private $validators;

    /**
     * @param string                       $file       The absolute cache path
     * @param MetadataValidatorInterface[] $validators The MetadataValidators to use for the freshness check
     */
    public function __construct($file, array $validators = array())
    {
        $this->file = $file;
        $this->validators = $validators;
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->file;
    }

    /**
     * Checks if the cache is still fresh.
     *
     * This implementation will make a decision solely based on the MetadataValidators
     * passed in the constructor.
     *
     * The first MetadataValidator that supports a given resource is considered authoritative.
     * Resources with no matching MetadataValidators will silently be ignored and considered fresh.
     *
     * @return bool true if the cache is fresh, false otherwise
     */
    public function isFresh()
    {
        if (!is_file($this->file)) {
            return false;
        }

        if (!$this->validators) {
            return true; // shortcut - if we don't have any validators we don't need to bother with the meta file at all
        }

        $metadata = $this->getMetaFile();
        if (!is_file($metadata)) {
            return true;
        }

        $time = filemtime($this->file);
        $meta = unserialize(file_get_contents($metadata));

        foreach ($meta as $resource) {
            foreach ($this->validators as $validator) {
                if (!$validator->supports($resource)) {
                    continue; // next validator
                }
                if ($validator->isFresh($resource, $time)) {
                    break; // no need to further check this resource
                }

                return false; // cache is stale
            }
            // no suitable validator found, ignore this resource
        }

        return true;
    }

    /**
     * Writes cache.
     *
     * @param string $content  The content to write in the cache
     * @param array  $metadata An array of metadata
     *
     * @throws \RuntimeException When cache file can't be written
     */
    public function write($content, array $metadata = null)
    {
        $mode = 0666;
        $umask = umask();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($this->file, $content, null);
        try {
            $filesystem->chmod($this->file, $mode, $umask);
        } catch (IOException $e) {
            // discard chmod failure (some filesystem may not support it)
        }

        if (null !== $metadata) {
            $filesystem->dumpFile($this->getMetaFile(), serialize($metadata), null);
            try {
                $filesystem->chmod($this->getMetaFile(), $mode, $umask);
            } catch (IOException $e) {
                // discard chmod failure (some filesystem may not support it)
            }
        }
    }

    /**
     * Gets the meta file path.
     *
     * @return string The meta file path
     */
    private function getMetaFile()
    {
        return $this->file.'.meta';
    }
}
