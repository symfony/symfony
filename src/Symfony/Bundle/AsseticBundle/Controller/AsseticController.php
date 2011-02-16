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
    protected $response;
    protected $am;
    protected $cache;

    public function __construct(Request $request, Response $response, AssetManager $am, CacheInterface $cache)
    {
        $this->request = $request;
        $this->response = $response;
        $this->am = $am;
        $this->cache = $cache;
    }

    public function render($name)
    {
        if (!$this->am->has($name)) {
            throw new NotFoundHttpException('Asset Not Found');
        }

        $asset = $this->getAsset($name);

        // validate if-modified-since
        if (null !== $lastModified = $asset->getLastModified()) {
            $date = new \DateTime();
            $date->setTimestamp($lastModified);
            $this->response->setLastModified($date);

            if ($this->response->isNotModified($this->request)) {
                return $this->response;
            }
        }

        $this->response->setContent($asset->dump());

        return $this->response;
    }

    protected function getAsset($name)
    {
        return new AssetCache($this->am->get($name), $this->cache);
    }
}
