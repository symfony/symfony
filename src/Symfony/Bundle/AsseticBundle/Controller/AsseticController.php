<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Controller;

use Assetic\Asset\AssetCache;
use Assetic\Factory\LazyAssetManager;
use Assetic\Cache\CacheInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves assets.
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony.com>
 */
class AsseticController
{
    protected $request;
    protected $am;
    protected $cache;

    public function __construct(Request $request, LazyAssetManager $am, CacheInterface $cache)
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
        $response = $this->createResponse();

        // last-modified
        if (null !== $lastModified = $asset->getLastModified()) {
            $date = new \DateTime();
            $date->setTimestamp($lastModified);
            $response->setLastModified($date);
        }

        // etag
        if ($this->am->hasFormula($name)) {
            $formula = $this->am->getFormula($name);
            $formula['last_modified'] = $lastModified;
            $response->setETag(md5(serialize($formula)));
        }

        if ($response->isNotModified($this->request)) {
            return $response;
        }

        $response->setContent($asset->dump());

        return $response;
    }

    protected function createResponse()
    {
        return new Response();
    }

    protected function getAsset($name)
    {
        return new AssetCache($this->am->get($name), $this->cache);
    }
}
