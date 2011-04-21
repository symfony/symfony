<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Factory\Worker;

use Assetic\Asset\AssetInterface;
use Assetic\Factory\Worker\WorkerInterface;

/**
 * Prepends a fake front controller so the asset knows where it is-ish.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class UseControllerWorker implements WorkerInterface
{
    public function process(AssetInterface $asset)
    {
        $targetUrl = $asset->getTargetUrl();
        if ($targetUrl && '/' != $targetUrl[0] && 0 !== strpos($targetUrl, '_controller/')) {
            $asset->setTargetUrl('_controller/'.$targetUrl);
        }

        return $asset;
    }
}
