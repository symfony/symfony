<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;

/**
 * AjaxDataCollector.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class AjaxDataCollector implements DataCollectorInterface
{
    public function getCollectedData()
    {
        return new AjaxData();
    }
}