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

/**
 * ConfigCache is the backwards-compatible way of using the new
 * cache implementation classes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ConfigCache implements ConfigCacheInterface
{
    private $cacheImplementation;

    /**
     * Constructor.
     *
     * @param string  $file  The absolute cache path
     * @param Boolean $debug Whether debugging is enabled or not
     */
    public function __construct($file, $debug)
    {
        $factory = new DefaultConfigCacheFactory($debug);
        $this->cacheImplementation = $factory->createCache($file);
    }

    public function __toString()
    {
        return $this->cacheImplementation->__toString();
    }

    public function isFresh()
    {
        return $this->cacheImplementation->isFresh();
    }

    public function write($content, array $metadata = null)
    {
        $this->cacheImplementation->write($content, $metadata);
    }
}
