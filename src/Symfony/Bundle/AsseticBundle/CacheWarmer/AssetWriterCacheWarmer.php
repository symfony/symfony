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
use Symfony\Bundle\AsseticBundle\Event\WriteEventArgs;
use Symfony\Bundle\AsseticBundle\Events;
use Doctrine\Common\EventManager;

class AssetWriterCacheWarmer extends CacheWarmer
{
    protected $am;
    protected $writer;
    protected $evm;

    public function __construct(AssetManager $am, AssetWriter $writer, EventManager $evm)
    {
        $this->am = $am;
        $this->writer = $writer;
        $this->evm = $evm;
    }

    public function warmUp($cacheDir)
    {
        // notify an event so custom stream wrappers can be registered lazily
        $this->evm->dispatchEvent(Events::onAsseticWrite, new WriteEventArgs());

        $this->writer->writeManagerAssets($this->am);
    }

    public function isOptional()
    {
        return true;
    }
}
