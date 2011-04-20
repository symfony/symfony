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

use Assetic\AssetWriter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AssetWriterCacheWarmer extends CacheWarmer
{
    private $container;
    private $writer;

    public function __construct(ContainerInterface $container, AssetWriter $writer)
    {
        $this->container = $container;
        $this->writer = $writer;
    }

    public function warmUp($cacheDir)
    {
        $am = $this->container->get('assetic.asset_manager');
        $this->writer->writeManagerAssets($am);
    }

    public function isOptional()
    {
        return true;
    }
}
