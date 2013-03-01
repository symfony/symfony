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
 * Abstract base implementation for ConfigCacheFactories.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
abstract class AbstractConfigCacheFactory implements ConfigCacheFactoryInterface
{

    abstract public function createCache($cacheFilename);

    public function cache($file, $callback)
    {
        $cache = $this->createCache($file);
        if (!$cache->isFresh()) call_user_func($callback, $cache);
        return $cache;
    }

}
