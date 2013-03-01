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

use Symfony\Component\Config\Resource\DefaultResourceValidator;

/**
 * Default implementation of ConfigCacheFactoryInterface
 *
 * @author Benjamin Klotz <bk@webfactory.de>
 */
class DefaultConfigCacheFactory implements ConfigCacheFactoryInterface
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

    public function createCache($cacheFilename)
    {
        switch ($this->debug) {
            case false:
                return new NonvalidatingCache($cacheFilename);

            case true:
                $cache = new ResourceValidatingCache($cacheFilename);
                $cache->addResourceValidator(new DefaultResourceValidator());

                return $cache;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cache($file, $callback)
    {
        $cache = $this->createCache($file);
        if (!$cache->isFresh()) call_user_func($callback, $cache);
        return $cache;
    }

}
