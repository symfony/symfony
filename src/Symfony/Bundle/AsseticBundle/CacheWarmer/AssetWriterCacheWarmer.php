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

use Assetic\AssetManager;
use Assetic\AssetWriter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;

class AssetWriterCacheWarmer extends CacheWarmer
{
    protected $am;
    protected $writer;

    public function __construct(AssetManager $am, AssetWriter $writer)
    {
        $this->am = $am;
        $this->writer = $writer;
    }

    public function warmUp($cacheDir)
    {
        $this->writer->writeManagerAssets($this->am);
    }

    public function isOptional()
    {
        return true;
    }
}
