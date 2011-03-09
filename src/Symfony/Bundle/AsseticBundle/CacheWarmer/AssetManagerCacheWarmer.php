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

use Assetic\Factory\LazyAssetManager;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;

class AssetManagerCacheWarmer extends CacheWarmer
{
    protected $am;

    public function __construct(LazyAssetManager $am)
    {
        $this->am = $am;
    }

    public function warmUp($cacheDir)
    {
        $this->am->load();
    }

    public function isOptional()
    {
        return true;
    }
}
