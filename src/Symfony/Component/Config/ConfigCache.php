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

use Symfony\Component\Config\Resource\BCResourceInterfaceChecker;
use Symfony\Component\Config\Resource\SelfCheckingResourceChecker;

/**
 * ConfigCache caches arbitrary content in files on disk.
 *
 * When in debug mode, those metadata resources that implement
 * \Symfony\Component\Config\Resource\SelfCheckingResourceInterface will
 * be used to check cache freshness.
 *
 * During a transition period, also instances of
 * \Symfony\Component\Config\Resource\ResourceInterface will be checked
 * by means of the isFresh() method. This behaviour is deprecated since 2.8
 * and will be removed in 3.0.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ConfigCache extends ResourceCheckerConfigCache
{
    private $debug;

    /**
     * @param string $file  The absolute cache path
     * @param bool   $debug Whether debugging is enabled or not
     */
    public function __construct($file, $debug)
    {
        parent::__construct($file, array(
            new SelfCheckingResourceChecker(),
            new BCResourceInterfaceChecker(),
        ));
        $this->debug = (bool) $debug;
    }

    /**
     * Gets the cache file path.
     *
     * @return string The cache file path
     *
     * @deprecated since 2.7, to be removed in 3.0. Use getPath() instead.
     */
    public function __toString()
    {
        @trigger_error('ConfigCache::__toString() is deprecated since version 2.7 and will be removed in 3.0. Use the getPath() method instead.', E_USER_DEPRECATED);

        return $this->getPath();
    }

    /**
     * Checks if the cache is still fresh.
     *
     * This implementation always returns true when debug is off and the
     * cache file exists.
     *
     * @return bool true if the cache is fresh, false otherwise
     */
    public function isFresh()
    {
        if (!$this->debug && is_file($this->getPath())) {
            return true;
        }

        return parent::isFresh();
    }
}
