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
 * Default implementation of ConfigCacheFactoryInterface
 *
 * @author Benjamin Klotz <bk@webfactory.de>
 */
class ConfigCacheFactory implements ConfigCacheFactoryInterface 
{
    protected $debug;

    /**
     * Constructor.
     *
     * @param bool $debug Whether to enable debugging or not
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
        $cache = new ConfigCache($file, $this->debug);
        if (!$cache->isFresh()) call_user_func($callback, $cache);
        return $cache;
    }

}
