<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Controller;

use Assetic\Asset\AssetCache;
use Assetic\AssetManager;
use Assetic\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves assets.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 */
class AsseticController
{
    protected $request;
    protected $am;
    protected $cache;

    public function __construct(Request $request, AssetManager $am, CacheInterface $cache)
    {
        $this->request = $request;
        $this->am = $am;
        $this->cache = $cache;
    }

    public function render($name)
    {
        if (!$this->am->has($name)) {
            throw new NotFoundHttpException('Asset Not Found');
        }

        $asset = $this->getAsset($name);

        $response = new Response();

        // validate if-modified-since
        if (null !== $lastModified = $asset->getLastModified()) {
            $date = new \DateTime();
            $date->setTimestamp($lastModified);
            $response->setLastModified($date);

            if ($response->isNotModified($this->request)) {
                return $response;
            }
        }

        $response->setContent($asset->dump());

        return $response;
    }

    protected function getAsset($name)
    {
        return new AssetCache($this->am->get($name), $this->cache);
    }
}
