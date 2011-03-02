<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\CacheWarmer;

use Assetic\AssetManager;
use Assetic\AssetWriter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmer;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AssetWriterCacheWarmer extends CacheWarmer
{
    protected $am;
    protected $writer;
    protected $dispatcher;

    public function __construct(AssetManager $am, AssetWriter $writer, EventDispatcher $dispatcher)
    {
        $this->am = $am;
        $this->writer = $writer;
        $this->dispatcher = $dispatcher;
    }

    public function warmUp($cacheDir)
    {
        // notify an event so custom stream wrappers can be registered lazily
        $this->dispatcher->notify(new Event(null, 'assetic.write'));

        $this->writer->writeManagerAssets($this->am);
    }

    public function isOptional()
    {
        return true;
    }
}
