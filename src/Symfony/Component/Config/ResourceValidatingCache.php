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

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * ConfigCache manages PHP cache files.
 *
 * When debug is enabled, it knows when to flush the cache
 * thanks to an array of ResourceInterface instances.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ResourceValidatingCache extends NonvalidatingCache
{

    /**
     * Checks if the cache is still fresh.
     *
     * This method evaluates the resources/metadata passed to the
     * write() method.
     *
     * @return Boolean true if the cache is fresh, false otherwise
     */
    public function isFresh()
    {
        if (!parent::isFresh()) {
            return false;
        }

        $metadata = $this->getMetaFile();
        if (!is_file($metadata)) {
            return false;
        }

        $time = filemtime($this->file);
        $meta = unserialize(file_get_contents($metadata));
        foreach ($meta as $resource) {
            if (!$resource->isFresh($time)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Writes cache.
     *
     * @param string              $content  The content to write in the cache
     * @param ResourceInterface[] $metadata An array of ResourceInterface instances
     *
     * @throws \RuntimeException When cache file can't be wrote
     */
    public function write($content, array $metadata = null)
    {
        if (null !== $metadata) {
            $filesystem = new Filesystem();
            $filesystem->dumpFile($this->getMetaFile(), serialize($metadata), 0666 & ~umask());
        }

        parent::write($content);
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
