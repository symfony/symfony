<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\CacheWarmer;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The AssetManagerCacheWarmer warms up the formula loader.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class AssetManagerCacheWarmer implements CacheWarmerInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function warmUp($cacheDir)
    {
        $am = $this->container->get('assetic.asset_manager');
        $am->load();
    }

    public function isOptional()
    {
        return true;
    }
}
