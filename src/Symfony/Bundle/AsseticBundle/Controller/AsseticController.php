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

use Assetic\AssetManager;
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

    public function __construct(Request $request, Response $response, AssetManager $am)
    {
        $this->request = $request;
        $this->response = $response;
        $this->am = $am;
    }

    public function render($name)
    {
        if (!$this->am->has($name)) {
            throw new NotFoundHttpException('Asset Not Found');
        }

        $asset = $this->am->get($name);

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
}
