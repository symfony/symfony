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
 * Basic implementation for ConfigCacheFactoryInterface
 * that will simply create an instance of ConfigCache.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ConfigCacheFactory implements ConfigCacheFactoryInterface
{
    /**
     * @var bool Debug flag passed to the ConfigCache
     */
    private $debug;

    /**
     * @param bool $debug The debug flag to pass to ConfigCache
     */
    public function __construct($debug)
    {
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function cache($file, $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException(sprintf('Invalid type for callback argument. Expected callable, but got "%s".', gettype($callback)));
        }

        $cache = new ConfigCache($file, $this->debug);
        if (!$cache->isFresh()) {
            call_user_func($callback, $cache);
        }

        return $cache;
    }
}
