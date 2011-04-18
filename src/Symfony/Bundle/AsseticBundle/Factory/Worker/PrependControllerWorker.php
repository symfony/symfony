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
 * Prepends a fake front controller to every asset's target URL.
 *
 * This worker should only be added when the use_controller configuration
 * option is true. It changes the target URL to include the front controller.
 *
 * @author Kris Wallsmith <kris@symfony.com>
 */
class PrependControllerWorker implements WorkerInterface
{
    const CONTROLLER = 'front_controller/';

    public function process(AssetInterface $asset)
    {
        $targetUrl = $asset->getTargetUrl();

        if ($targetUrl && '/' != $targetUrl[0] && 0 !== strpos($targetUrl, self::CONTROLLER)) {
            $asset->setTargetUrl(self::CONTROLLER.$targetUrl);
        }
    }
}
