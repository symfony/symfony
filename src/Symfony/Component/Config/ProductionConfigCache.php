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
use Symfony\Component\Config\Util\CacheFileUtils;

/**
 * A cache that never expires once it has been written.
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProductionConfigCache implements ConfigCacheInterface
{
    protected $file;

    /**
     * Constructor.
     *
     * @param string  $file  The absolute cache path
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Gets the cache file path.
     *
     * @return string The cache file path
     */
    public function __toString()
    {
        return $this->file;
    }

    public function isFresh()
    {
        if (!is_file($this->file)) {
            return false;
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
        CacheFileUtils::dumpInFile($this->file, $content);
    }
}
