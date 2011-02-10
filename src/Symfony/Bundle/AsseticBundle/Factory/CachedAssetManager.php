<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory;

use Assetic\Factory\LazyAssetManager;

/**
 * Loads asset formulae from the filesystem.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class CachedAssetManager extends LazyAssetManager
{
    protected $cacheFiles = array();
    protected $fresh = true;

    /**
     * Adds a cache file.
     *
     * Files added will be lazily loaded once needed.
     *
     * @param string $file A file that returns an array of formulae
     */
    public function addCacheFile($file)
    {
        $this->cacheFiles[] = $file;
        $this->fresh = false;
    }

    public function getFormulae()
    {
        if (!$this->fresh) {
            $this->loadCacheFiles();
        }

        return $this->formulae;
    }

    public function get($name)
    {
        if (!$this->fresh) {
            $this->loadCacheFiles();
        }

        return parent::get($name);
    }

    public function has($name)
    {
        if (!$this->fresh) {
            $this->loadCacheFiles();
        }

        return parent::has($name);
    }

    public function all()
    {
        if (!$this->fresh) {
            $this->loadCacheFiles();
        }

        return parent::all();
    }

    /**
     * Loads formulae from the cache files.
     */
    protected function loadCacheFiles()
    {
        while ($file = array_shift($this->cacheFiles)) {
            if (!file_exists($file)) {
                throw new \RuntimeException('The asset manager cache has not been warmed.');
            }

            $this->addFormulae(require $file);
        }

        $this->fresh = true;
    }
}
